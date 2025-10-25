<?php

namespace App\Services;

use App\Models\IngestedLaw;
use App\Models\LawUpload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LawIngestService
{
    public function __construct(
        protected LawFetcher $fetcher,
        protected LawParser $parser,
        protected LawVectorStoreService $vectorStore,
        protected MetadataBuilder $meta,
        protected PdfRenderer $pdfRenderer,
    ) {
    }

    /**
     * Ingest latest consolidated laws from Narodne Novine.
     * Adds full and per-article PDF storage and writes vectors into laws table.
     * Options: since_year, max_acts, model, agent, namespace
     */
    public function ingest(array $options = []): array
    {
        $sinceYear = isset($options['since_year']) ? (int)$options['since_year'] : null;
        $maxActs = isset($options['max_acts']) ? (int)$options['max_acts'] : null;
        $model = $options['model'] ?? config('openai.models.embeddings');
        $agent = $options['agent'] ?? 'law';
        $namespace = $options['namespace'] ?? 'nn';

        $countActs = 0; $countArticles = 0; $countInserted = 0; $errors = 0; $skippedActs = 0; $skips = [];
        foreach ($this->fetcher->latestConsolidations($sinceYear) as $law) {
            if ($maxActs && $countActs >= $maxActs) break;

            try {
                $htmlUrl = $law['html_url'] ?? null;
                if (!$htmlUrl) { $countActs++; continue; }

                // Derive stable doc_id early for dedupe
                $docId = $law['eli_resource'] ?? $law['eli_expression'] ?? ('nn-'.$law['year'].'-'.$law['edition'].'-'.$law['act']);

                // Deduplication: skip if already ingested
                $already = IngestedLaw::query()
                    ->where('doc_id', $docId)
                    ->orWhere(function($q) use ($htmlUrl) { if ($htmlUrl) $q->where('source_url', $htmlUrl); })
                    ->first();
                if ($already) {
                    $skippedActs++;
                    $countActs++;
                    $msg = 'Skipping already ingested law: '.$docId.' ('.$htmlUrl.')';
                    $skips[] = $msg;
                    Log::info($msg);
                    continue;
                }

                $html = $this->fetchWithRetry($htmlUrl, 'law_html');

                // persist source for traceability
                $baseDir = sprintf('laws/%s/%s/act-%s', $law['year'], $law['edition'], $law['act']);
                Storage::put($baseDir.'/source.html', $html);

                // Create parent IngestedLaw record (ownership for embeddings and uploads)
                $ingested = IngestedLaw::create([
                    'id' => (string) Str::ulid(),
                    'doc_id' => $docId,
                    'title' => (string)($law['title'] ?? ''),
                    'law_number' => (string)($law['act'] ?? ''),
                    'jurisdiction' => 'HR',
                    'country' => 'HR',
                    'language' => 'hr',
                    'source_url' => $htmlUrl,
                    'aliases' => array_values(array_filter([
                        $docId,
                        $law['eli_resource'] ?? null,
                        $law['eli_expression'] ?? null,
                        (string)($law['title'] ?? null),
                    ])),
                    'keywords' => array_values(array_filter([
                        (string)($law['title'] ?? ''),
                        'godina:'.(string)($law['year'] ?? ''),
                        'broj:'.(string)($law['edition'] ?? ''),
                        'akt:'.(string)($law['act'] ?? ''),
                        (string)($law['type_document'] ?? ''),
                    ])),
                    'keywords_text' => implode(' ', array_values(array_filter([
                        (string)($law['title'] ?? ''),
                        (string)($law['year'] ?? ''),
                        (string)($law['edition'] ?? ''),
                        (string)($law['act'] ?? ''),
                        (string)($law['type_document'] ?? ''),
                    ]))),
                    'metadata' => $law,
                    'ingested_at' => now(),
                ]);

                // Download full PDF if available
                $fullPdfUrl = $law['pdf_url'] ?? null;
                if ($fullPdfUrl) {
                    try {
                        $pdfContent = $this->fetchWithRetry($fullPdfUrl, 'law_pdf', true);
                        $pdfRelPath = $baseDir.'/full.pdf';
                        Storage::put($pdfRelPath, $pdfContent);
                        $this->recordLawUpload($ingested->id, $docId, $pdfRelPath, basename(parse_url($fullPdfUrl, PHP_URL_PATH)) ?: 'full.pdf', $fullPdfUrl);
                    } catch (\Throwable $e) {
                        Log::warning('Failed downloading law PDF after retries', [
                            'url' => $fullPdfUrl,
                            'error' => $e->getMessage(),
                            'doc_id' => $docId,
                        ]);
                    }
                }

                $articles = $this->parser->splitIntoArticles($html);
                if (empty($articles)) { $countActs++; continue; }

                $docs = [];
                foreach ($articles as $idx => $art) {
                    $plain = trim($this->htmlToText($art['html'] ?? ''));
                    if ($plain === '') continue;

                    $articleNumber = (string)($art['number'] ?? $idx+1);

                    // Render per-article PDF and store upload record
                    $pdfFileName = sprintf('%s - clanak-%s.pdf', trim((string)$law['title']), $articleNumber);
                    $pdfRelPath = $baseDir.'/'.$pdfFileName;
                    $this->pdfRenderer->renderArticle([
                        'law_title' => (string)($law['title'] ?? ''),
                        'law_eli' => (string)($law['eli_resource'] ?? ''),
                        'law_pub_date' => (string)($law['date_publication'] ?? ''),
                        'article_number' => $articleNumber,
                        'article_html' => $art['html'] ?? '',
                        'generated_at' => gmdate('c'),
                        'generator_version' => '1.0.0',
                        'search_tags' => array_values(array_filter([ (string)($law['title'] ?? ''), 'članak '.$articleNumber, $law['date_publication'] ?? null ])),
                    ], Storage::path($pdfRelPath));
                    $this->recordLawUpload($ingested->id, $docId, $pdfRelPath, $pdfFileName, $htmlUrl);

                    $ctx = [
                        'year' => $law['year'],
                        'edition' => $law['edition'],
                        'act' => $law['act'],
                        'eli_resource' => $law['eli_resource'] ?? null,
                        'eli_expression' => $law['eli_expression'] ?? null,
                        'title' => $law['title'],
                        'date_publication' => $law['date_publication'],
                        'html_url' => $law['html_url'],
                        'pdf_url' => $law['pdf_url'] ?? null,
                        'type_document' => $law['type_document'] ?? null,
                        'article_number' => $articleNumber,
                        'heading_chain' => $art['heading_chain'] ?? [],
                        'text_checksum' => hash('sha256', $plain),
                        'file_path' => $pdfRelPath,
                        'file_bytes' => Storage::exists($pdfRelPath) ? Storage::size($pdfRelPath) : null,
                        'file_sha256' => ($p = Storage::path($pdfRelPath)) && is_file($p) ? hash_file('sha256', $p) : null,
                    ];

                    // Build law metadata ensuring all fields are present uniformly
                    $lawMeta = [
                        'title' => $law['title'] ?? null,
                        'law_number' => (string)($law['act'] ?? ''),
                        'jurisdiction' => 'HR',
                        'country' => 'HR',
                        'language' => 'hr',
                        'version' => 'consolidated',
                        'promulgation_date' => $law['date_publication'] ?? null,
                        'effective_date' => $law['date_publication'] ?? null, // Consolidated laws are effective
                        'source_url' => $law['html_url'] ?? null,
                        // Tags should be actual tags, not heading_chain
                        'tags' => array_values(array_filter([
                            $law['type_document'] ?? null,
                            'consolidated',
                            'HR',
                        ])),
                    ];

                    $docs[] = [
                        'content' => $plain,
                        'metadata' => $this->meta->buildArticleMetadata($ctx),
                        'chunk_index' => 0,
                        'law_meta' => $lawMeta,
                    ];
                    $countArticles++;
                }

                if (!empty($docs)) {
                    $res = $this->vectorStore->ingest($docId, $docs, [
                        'model' => $model,
                        'provider' => 'openai',
                        'base_meta' => [],
                        'ingested_law_id' => $ingested->id,
                    ]);
                    $countInserted += (int)($res['inserted'] ?? 0);
                }
                $countActs++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Law ingest failed', ['error' => $e->getMessage(), 'law' => $law]);
            }
        }

        return [
            'acts_processed' => $countActs,
            'articles_seen' => $countArticles,
            'inserted' => $countInserted,
            'skipped' => $skippedActs,
            'skip_messages' => $skips,
            'errors' => $errors,
            'model' => $model,
            'agent' => $agent,
            'namespace' => $namespace,
        ];
    }

    protected function recordLawUpload(string $ingestedLawId, string $docId, string $relPath, string $originalName, ?string $sourceUrl = null): void
    {
        $disk = 'local';
        $abs = Storage::path($relPath);
        $size = is_file($abs) ? @filesize($abs) : null;
        $sha = is_file($abs) ? @hash_file('sha256', $abs) : null;

        LawUpload::create([
            'id' => (string) Str::ulid(),
            'doc_id' => $docId,
            'ingested_law_id' => $ingestedLawId,
            'disk' => $disk,
            'local_path' => $relPath,
            'original_filename' => $originalName,
            'mime_type' => 'application/pdf',
            'file_size' => $size,
            'sha256' => $sha,
            'source_url' => $sourceUrl,
            'downloaded_at' => now(),
            'status' => 'stored',
        ]);
    }

    protected function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '');
        return trim($text);
    }

    /**
     * Fetch URL with exponential backoff and jitter
     *
     * Implements retry logic with:
     * - Exponential backoff: 1s, 2s, 4s delays
     * - Random jitter (0-50% of delay) to avoid thundering herd
     * - Structured logging for all attempts and failures
     * - Content-type validation for PDFs
     *
     * @param string $url URL to fetch
     * @param string $context Context for logging (e.g., 'law_html', 'law_pdf')
     * @param bool $isPdf Whether to validate PDF content type
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @return string Response body
     * @throws \Exception If all retries are exhausted
     */
    protected function fetchWithRetry(string $url, string $context, bool $isPdf = false, int $maxRetries = 3): string
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $response = Http::timeout(60)->get($url);

                if ($response->successful()) {
                    // For PDFs, verify content type
                    if ($isPdf && stripos($response->header('Content-Type', ''), 'pdf') === false) {
                        throw new \Exception("Expected PDF content type, got: " . $response->header('Content-Type'));
                    }

                    if ($attempt > 1) {
                        Log::info('HTTP fetch succeeded after retry', [
                            'url' => $url,
                            'context' => $context,
                            'attempt' => $attempt,
                            'is_pdf' => $isPdf,
                        ]);
                    }

                    return $response->body();
                }

                throw new \Exception("HTTP {$response->status()}");

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning('HTTP fetch failed', [
                    'url' => $url,
                    'context' => $context,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'is_pdf' => $isPdf,
                    'error' => $e->getMessage(),
                ]);

                // Don't sleep after the last attempt
                if ($attempt < $maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    Log::debug('Retrying HTTP fetch after delay', [
                        'url' => $url,
                        'delay_ms' => $delay,
                        'next_attempt' => $attempt + 1,
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        // All retries exhausted
        Log::error('HTTP fetch failed after all retries', [
            'url' => $url,
            'context' => $context,
            'total_attempts' => $attempt,
            'is_pdf' => $isPdf,
            'error' => $lastException->getMessage(),
        ]);

        throw new \Exception(
            "Failed to fetch {$context} after {$maxRetries} attempts: {$url}",
            0,
            $lastException
        );
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * Formula: base_delay * 2^(attempt-1) + random_jitter
     * Example delays:
     * - Attempt 1: 1000ms + jitter (1000-1500ms)
     * - Attempt 2: 2000ms + jitter (2000-3000ms)
     * - Attempt 3: 4000ms + jitter (4000-6000ms)
     *
     * Jitter helps distribute retry attempts and avoid thundering herd problem
     * when multiple processes are retrying simultaneously.
     *
     * @param int $attempt Attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected function calculateBackoffDelay(int $attempt): int
    {
        // Exponential backoff: base_delay * 2^(attempt-1)
        $baseDelay = config('services.law_ingest.retry_base_delay', 1000); // 1 second default
        $exponentialDelay = $baseDelay * pow(2, $attempt - 1);

        // Add jitter: random value between 0 and 50% of the delay
        $jitterPercent = config('services.law_ingest.retry_jitter_percent', 0.5);
        $jitter = rand(0, (int)($exponentialDelay * $jitterPercent));

        return (int)($exponentialDelay + $jitter);
    }
}
