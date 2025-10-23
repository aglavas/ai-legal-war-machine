<?php

namespace App\Pipelines\Textract;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\OpenAIService;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersistReconstructedStep
{
    public function __construct(
        private LegalMetadataExtractor $metadataExtractor
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = (string) $payload['driveFileId'];
        $fileName = (string) $payload['driveFileName'];
        $pdfPath = (string) $payload['targetLocalPath'];
        $jsonPath = (string) ($payload['resultsMeta']['localJsonAbs'] ?? '');
        $s3Input = (string) ($payload['s3Key'] ?? '');
        $s3Json = (string) ($payload['resultsMeta']['s3JsonKey'] ?? '');
        $s3Output = (string) ($payload['outKey'] ?? '');

        // Require a valid selected case
        $caseId = (string) ($payload['caseId'] ?? '');
        if ($caseId === '') {
            throw new \RuntimeException('Missing case selection.');
        }
        $case = LegalCase::query()->find($caseId);
        if (!$case) {
            throw new \RuntimeException('Selected case not found: ' . $caseId);
        }

        // Normalize relative path under the 'local' disk root
        $localRoot = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $localRel = ltrim(Str::replaceFirst($localRoot, '', $pdfPath), '/');

        $size = is_file($pdfPath) ? filesize($pdfPath) : 0;
        $sha = is_file($pdfPath) ? hash_file('sha256', $pdfPath) : null;

        $upload = new CaseDocumentUpload([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'doc_id' => 'doc-' . $driveFileId,
            'disk' => 'local',
            'local_path' => $localRel,
            'original_filename' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => $size,
            'sha256' => $sha,
            'status' => 'stored',
            'uploaded_at' => now(),
            'source_url' => null,
        ]);
        $upload->save();

        // Gather text content from OCR document
        $doc = $payload['ocrDocument'] ?? null;
        $fullText = '';
        if ($doc && isset($doc->pages)) {
            foreach ($doc->pages as $page) {
                if (!isset($page->lines)) continue;
                foreach ($page->lines as $line) {
                    $txt = $line->text ?? '';
                    if ($txt !== '') $fullText .= $txt . "\n";
                }
                $fullText .= "\n"; // page break
            }
        }

        $fullText = trim($fullText);

        // Get legal metadata from payload (already extracted by CreateMetadataStep)
        // or extract it if not available (standalone usage)
        // This provides the same rich metadata as TextractJob (citations, courts, parties, etc.)
        $legalMetadata = $payload['legalMetadata'] ?? null;

        if (!$legalMetadata && $doc) {
            try {
                $legalMetadata = $this->metadataExtractor->extract(
                    document: $doc,
                    driveFileId: $driveFileId,
                    driveFileName: $fileName
                );
                Log::info('Legal metadata extracted for case document', [
                    'driveFileId' => $driveFileId,
                    'documentType' => $legalMetadata->documentType,
                    'totalCitations' => $legalMetadata->totalCitations,
                    'courts' => count($legalMetadata->courts),
                    'parties' => count($legalMetadata->parties),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Legal metadata extraction failed', [
                    'driveFileId' => $driveFileId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Chunking
        $chunkCfg = config('vizra-adk.vector_memory.chunking', []);
        $size = (int) ($chunkCfg['chunk_size'] ?? 1000);
        $overlap = (int) ($chunkCfg['overlap'] ?? 200);
        if ($size < 100) $size = 1000;
        if ($overlap < 0) $overlap = 0;
        $chunks = $this->chunkText($fullText, $size, $overlap);

        // Embeddings
        $embeddingProvider = (string) config('vizra-adk.vector_memory.embedding_provider', 'openai');
        $embeddingModel = null;
        if ($embeddingProvider === 'openai') {
            $embeddingModel = (string) config('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-small');
        }

        $vectors = [];
        try {
            if ($embeddingProvider === 'openai' && count($chunks) > 0) {
                /** @var OpenAIService $openai */
                $openai = app(OpenAIService::class);
                $resp = $openai->embeddings(array_values($chunks), $embeddingModel ?: null);
                $vectors = $resp['data'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('Embedding generation failed', ['error' => $e->getMessage()]);
        }

        $dims = (int) (config("vizra-adk.vector_memory.dimensions.$embeddingModel") ?? 0);

        // Create document chunks
        foreach ($chunks as $i => $text) {
            $vec = $vectors[$i]['embedding'] ?? null;
            $norm = is_array($vec) ? $this->l2norm($vec) : null;

            $row = new CaseDocument([
                'id' => (string) Str::ulid(),
                'case_id' => $case->id,
                'doc_id' => 'doc-' . $driveFileId,
                'upload_id' => $upload->id,
                'title' => $fileName . ' (chunk ' . ($i + 1) . '/' . max(1, count($chunks)) . ')',
                'category' => 'textract',
                'language' => null,
                'tags' => ['textract', 'reconstructed'],
                'chunk_index' => $i,
                'content' => $text,
                'metadata' => [
                    'drive_file_id' => $driveFileId,
                    's3_input_key' => $s3Input,
                    's3_json_key' => $s3Json,
                    's3_output_key' => $s3Output,
                    'local_json_path' => $jsonPath ? ltrim(Str::replaceFirst($localRoot, '', $jsonPath), '/') : null,
                    'local_pdf_path' => $localRel,
                ],
                'actual' => $legalMetadata?->toArray(),
                'source' => 'drive-textract',
                'source_id' => $driveFileId,
                'embedding_provider' => $embeddingProvider,
                'embedding_model' => $embeddingModel,
                'embedding_dimensions' => $dims ?: (is_array($vec) ? count($vec) : null),
                'embedding_norm' => $norm,
                'content_hash' => $text !== '' ? hash('sha256', $text) : null,
                'token_count' => null,
                'embedding_vector' => is_array($vec) ? $vec : null,
            ]);
            $row->save();
        }

        // Neo4j minimal upsert
        try {
            if ((bool) config('neo4j.sync.enabled', true)) {
                $neo = app(\App\Services\Neo4jService::class);
                $neo->upsertCaseAndDocument($case->id, $case->title ?? $case->case_number ?? $case->id, 'doc-' . $driveFileId, $fileName);
            }
        } catch (\Throwable $e) {
            Log::warning('Neo4j upsert failed', ['error' => $e->getMessage()]);
        }

        return $next($payload);
    }

    /**
     * Simple sliding window chunker by characters.
     * @param string $text
     * @param int $size
     * @param int $overlap
     * @return array<int,string>
     */
    private function chunkText(string $text, int $size, int $overlap): array
    {
        $text = trim($text);
        if ($text === '') return [];
        if ($size <= 0) return [$text];
        $chunks = [];
        $start = 0;
        $len = mb_strlen($text, 'UTF-8');
        while ($start < $len) {
            $end = min($len, $start + $size);
            $chunk = mb_substr($text, $start, $end - $start, 'UTF-8');
            $chunks[] = $chunk;
            if ($end >= $len) break;
            $start = max(0, $end - $overlap);
        }
        return $chunks;
    }

    private function l2norm(array $vec): ?float
    {
        if (!count($vec)) return null;
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += ((float)$v) * ((float)$v);
        }
        return sqrt($sum);
    }
}
