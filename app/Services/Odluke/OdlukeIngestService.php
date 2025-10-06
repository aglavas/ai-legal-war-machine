<?php

namespace App\Services\Odluke;

use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\CaseVectorStoreService;
use App\Services\IngestPipelineService;
use App\Services\MetadataBuilder;
use App\Services\OcrService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OdlukeIngestService
{
    public function __construct(
        protected OdlukeClient $client,
        protected IngestPipelineService $pipeline,
        protected CaseVectorStoreService $caseVectors,
        protected ?OcrService $ocr = null,
        protected ?MetadataBuilder $meta = null,
    ) {}

    /**
     * Ingest decisions by IDs: fetch HTML (preferred) or PDF, extract text, chunk and embed into cases_documents. Also store local PDF upload when used.
     * Options: model, chunk_chars, overlap, prefer ('auto'|'html'|'pdf'), dry
     */
    public function ingestByIds(array $ids, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int)($options['chunk_chars'] ?? 1500);
        $overlap = (int)($options['overlap'] ?? 200);
        $prefer = $options['prefer'] ?? 'auto';
        $dry = (bool)($options['dry'] ?? false);

        $inserted = 0; $count = 0; $errors = 0; $skipped = 0; $wouldChunks = 0;

        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id === '') continue;
            $count++;
            try {
                $meta = $this->client->fetchDecisionMeta($id) ?? [];

                // Upsert case using available meta
                $case = $this->upsertCaseFromMeta($meta);

                $htmlRes = null; $pdfRes = null; $text = '';
                $uploadId = null; $docId = $meta['ecli'] ?? $id;

                if ($prefer !== 'pdf') {
                    $htmlRes = $this->client->downloadHtml($id);
                    if (($htmlRes['ok'] ?? false) && is_string($htmlRes['bytes'])) {
                        $text = $this->htmlToText($htmlRes['bytes']);
                        $this->persistSource($id, $htmlRes['bytes'], 'html', $case);
                    }
                }
                if ($text === '' && $prefer !== 'html') {
                    $pdfRes = $this->client->downloadPdf($id);
                    if (($pdfRes['ok'] ?? false) && is_string($pdfRes['bytes'])) {
                        $this->persistSource($id, $pdfRes['bytes'], 'pdf', $case);
                        if (!$dry) {
                            $rel = $this->storePdfUpload($case, $id, $pdfRes['bytes']);
                            $uploadId = $rel['upload_id'] ?? null;
                        }
                        if ($this->ocr) {
                            $ex = $this->ocr->extractTextFromPdf($rel['abs_path'] ?? $this->tempFile($pdfRes['bytes'], '.pdf'));
                            if (is_string($ex)) $text = $ex;
                        }
                    }
                }

                $text = trim(preg_replace('/\s+/u', ' ', $text ?? ''));
                if ($text === '') { $skipped++; continue; }

                $chunks = $this->pipeline->chunkText($text, $chunkChars, $overlap);
                $wouldChunks += count($chunks);
                if (empty($chunks)) { $skipped++; continue; }

                if (!$dry) {
                    $docs = [];
                    foreach ($chunks as $ci => $content) {
                        $docs[] = [
                            'content' => $content,
                            'metadata' => $this->buildMetadata($meta, $id, $ci),
                            'chunk_index' => $ci,
                            'source' => $meta['src'] ?? $this->client->downloadHtmlUrl($id),
                            'source_id' => $id,
                        ];
                    }

                    $res = $this->caseVectors->ingest((string)$case->id, $docId, $docs, [
                        'model' => $model,
                        'provider' => 'openai',
                        'upload_id' => $uploadId,
                    ]);
                    $inserted += (int)($res['inserted'] ?? 0);
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Odluke ingest failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return [
            'ids_processed' => $count,
            'inserted' => $inserted,
            'would_chunks' => $wouldChunks,
            'errors' => $errors,
            'skipped' => $skipped,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    /** Persist original source for traceability under storage/app/cases/{case}/sources/{id}.{ext} */
    protected function persistSource(string $id, string $bytes, string $ext, LegalCase $case): void
    {
        $dir = $this->caseBaseDir($case) . '/sources';
        $path = $dir.'/'. $id .'.'. $ext;
        Storage::put($path, $bytes);
    }

    protected function storePdfUpload(LegalCase $case, string $id, string $bytes): array
    {
        $dir = $this->caseBaseDir($case) . '/pdfs';
        $fileName = 'decision-'.$id.'.pdf';
        $rel = $dir.'/'.$fileName;
        Storage::put($rel, $bytes);
        $abs = Storage::path($rel);

        $upload = CaseDocumentUpload::create([
            'id' => (string) Str::ulid(),
            'case_id' => (string) $case->id,
            'doc_id' => $id,
            'disk' => 'local',
            'local_path' => $rel,
            'original_filename' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => @filesize($abs) ?: null,
            'sha256' => @hash_file('sha256', $abs) ?: null,
            'source_url' => $this->client->downloadPdfUrl($id) ?? null,
            'uploaded_at' => now(),
            'status' => 'stored',
        ]);

        return ['upload_id' => (string) $upload->id, 'rel_path' => $rel, 'abs_path' => $abs];
    }

    protected function caseBaseDir(LegalCase $case): string
    {
        $court = Str::slug((string)($case->court ?? 'court'));
        $num = Str::slug((string)($case->case_number ?? (string)$case->id));
        return 'cases/'.$court.'/'.$num;
    }

    protected function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text ?? ''));
    }

    protected function tempFile(string $bytes, string $suffix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'odluke_');
        $path = $tmp . $suffix;
        @unlink($tmp);
        file_put_contents($path, $bytes);
        return $path;
    }

    protected function upsertCaseFromMeta(array $m): LegalCase
    {
        $caseNumber = trim((string)($m['broj_odluke'] ?? '')) ?: null;
        $court = trim((string)($m['sud'] ?? '')) ?: null;
        $attrs = [
            'title' => (string)($m['vrsta_odluke'] ?? 'Sudska odluka'),
            'client_name' => null,
            'opponent_name' => null,
            'court' => $court,
            'jurisdiction' => 'HR',
            'judge' => null,
            'filing_date' => $m['datum_objave'] ?? ($m['datum_odluke'] ?? null),
            'status' => null,
            'tags' => array_filter([
                $m['vrsta_odluke'] ?? null,
                $m['upisnik'] ?? null,
                $m['pravomocnost'] ?? null,
            ]),
            'description' => null,
        ];

        if ($caseNumber && $court) {
            $case = LegalCase::query()->where('case_number', $caseNumber)->where('court', $court)->first();
            if ($case) {
                $case->fill($attrs)->save();
                return $case;
            }
        }

        $payload = array_merge(['id' => (string) Str::ulid(), 'case_number' => $caseNumber], (array) $attrs);
        $case = new LegalCase($payload);
        $case->save();
        return $case;
    }

    protected function buildMetadata(array $m, string $id, int $chunkIndex): array
    {
        $ctx = [
            'id' => $id,
            'broj_odluke' => $m['broj_odluke'] ?? null,
            'sud' => $m['sud'] ?? null,
            'datum_odluke' => $m['datum_odluke'] ?? null,
            'pravomocnost' => $m['pravomocnost'] ?? null,
            'datum_objave' => $m['datum_objave'] ?? null,
            'upisnik' => $m['upisnik'] ?? null,
            'vrsta_odluke' => $m['vrsta_odluke'] ?? null,
            'ecli' => $m['ecli'] ?? null,
            'src' => $m['src'] ?? $this->client->downloadHtmlUrl($id),
            'chunk' => $chunkIndex,
        ];
        return $ctx;
    }

    /**
     * Offline ingestion from a plain text body representing a decision. Useful for validation.
     */
    public function ingestText(string $text, array $meta = [], array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $chunkChars = (int)($options['chunk_chars'] ?? 1500);
        $overlap = (int)($options['overlap'] ?? 200);
        $dry = (bool)($options['dry'] ?? false);

        $text = trim(preg_replace('/\s+/u', ' ', $text ?? ''));
        if ($text === '') {
            return ['ids_processed' => 0, 'inserted' => 0, 'would_chunks' => 0, 'errors' => 0, 'skipped' => 1, 'model' => $model, 'dry' => $dry];
        }

        // Upsert case
        $case = $this->upsertCaseFromMeta($meta);
        $docId = $meta['ecli'] ?? ('offline-'.substr(sha1($text), 0, 12));

        $chunks = $this->pipeline->chunkText($text, $chunkChars, $overlap);
        $would = count($chunks);
        $inserted = 0;

        if (!$dry && $would > 0) {
            $docs = [];
            foreach ($chunks as $ci => $content) {
                $docs[] = [
                    'content' => $content,
                    'metadata' => $this->buildMetadata($meta, $docId, $ci),
                    'chunk_index' => $ci,
                    'source' => $meta['src'] ?? null,
                    'source_id' => $docId,
                ];
            }
            $res = $this->caseVectors->ingest((string)$case->id, $docId, $docs, ['model' => $model, 'provider' => 'openai']);
            $inserted += (int)($res['inserted'] ?? 0);
        }

        return [
            'ids_processed' => 1,
            'inserted' => $inserted,
            'would_chunks' => $would,
            'errors' => 0,
            'skipped' => 0,
            'model' => $model,
            'dry' => $dry,
        ];
    }
}
