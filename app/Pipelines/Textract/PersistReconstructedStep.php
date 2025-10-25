<?php

namespace App\Pipelines\Textract;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\Ocr\LegalMetadataExtractor;
use App\Services\CaseIngestPipeline;
use App\Services\OpenAIService;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersistReconstructedStep
{
    public function __construct(
        private LegalMetadataExtractor $metadataExtractor,
        private CaseIngestPipeline $ingestPipeline
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

        // Get OCR quality metrics from payload if available
        $qualityMetrics = $payload['qualityMetrics'] ?? [];
        $blocks = $payload['blocks'] ?? [];

        // Prepare ingest options
        $chunkCfg = config('vizra-adk.vector_memory.chunking', []);
        $ingestOptions = [
            'chunk_size' => (int) ($chunkCfg['chunk_size'] ?? 1200),
            'overlap' => (int) ($chunkCfg['overlap'] ?? 150),
            'language' => 'hr',  // Croatian by default
            'min_confidence' => (float) config('vizra-adk.ocr.min_confidence', 0.82),
            'min_coverage' => (float) config('vizra-adk.ocr.min_coverage', 0.75),
            'skip_embedding_on_low_quality' => (bool) config('vizra-adk.ocr.skip_embedding_on_low_quality', false),
            'upload_id' => $upload->id,
            'model' => config('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-small'),
            'provider' => config('vizra-adk.vector_memory.embedding_provider', 'openai'),
            'metadata' => [
                'drive_file_id' => $driveFileId,
                'drive_file_name' => $fileName,
                's3_input_key' => $s3Input,
                's3_json_key' => $s3Json,
                's3_output_key' => $s3Output,
                'local_json_path' => $jsonPath ? ltrim(Str::replaceFirst($localRoot, '', $jsonPath), '/') : null,
                'local_pdf_path' => $localRel,
                'source' => 'drive-textract',
                'ocr_quality' => $qualityMetrics,
            ] + ($legalMetadata?->toArray() ?? []),
        ];

        // Use the CaseIngestPipeline for quality-gated ingestion
        try {
            $result = $this->ingestPipeline->ingest(
                caseId: $case->id,
                docId: 'doc-' . $driveFileId,
                rawText: $fullText,
                ocrBlocks: $blocks,
                options: $ingestOptions
            );

            Log::info('Document ingestion via pipeline', [
                'driveFileId' => $driveFileId,
                'status' => $result['status'],
                'chunks' => $result['chunk_count'] ?? 0,
                'needs_review' => $result['needs_review'],
            ]);

            // Store the ingest result in payload for downstream tracking
            $payload['ingestResult'] = $result;

        } catch (\Throwable $e) {
            Log::error('CaseIngestPipeline failed, falling back to direct save', [
                'driveFileId' => $driveFileId,
                'error' => $e->getMessage(),
            ]);

            // Fallback: Create a single document record without chunking/embedding
            $row = new CaseDocument([
                'id' => (string) Str::ulid(),
                'case_id' => $case->id,
                'doc_id' => 'doc-' . $driveFileId,
                'upload_id' => $upload->id,
                'title' => $fileName,
                'category' => 'textract',
                'language' => null,
                'tags' => ['textract', 'reconstructed', 'pipeline_failed'],
                'chunk_index' => 0,
                'content' => $fullText,
                'metadata' => $ingestOptions['metadata'],
                'actual' => $legalMetadata?->toArray(),
                'source' => 'drive-textract',
                'source_id' => $driveFileId,
                'content_hash' => hash('sha256', $fullText),
                'token_count' => null,
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
}
