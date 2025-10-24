<?php

namespace App\Services;

use App\Models\LawUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\IngestedLaw; // add import

class ZakonHrIngestService
{
    public function __construct(
        protected LawParser $parser,
        protected LawVectorStoreService $vectorStore,
        protected PdfRenderer $renderer,
        protected PdfMerger $merger,
    ) {}

    /**
     * Ingest one or more zakon.hr pages by URL. Fetches HTML, splits into articles, renders PDFs per article, merges full, and embeds into laws.
     * Options: model, title, date, dry
     *
     * TODO: For better progress feedback, consider:
     * - Dispatching Laravel events (e.g. LawImportProgress) that Livewire can listen to
     * - Moving to queue-based processing with job progress tracking
     * - Using cache or database to store real-time progress for async UI updates
     */
    public function ingestUrls(array $urls, array $options = []): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $dry = (bool)($options['dry'] ?? false);

        $totalArticles = 0; $totalInserted = 0; $errors = 0; $processed = 0; $wouldChunks = 0;
        $totalUrls = count($urls);

        foreach ($urls as $idx => $url) {
            $url = trim((string)$url);
            if ($url === '') continue;

            // Check cache to avoid re-processing recently imported laws
            $cacheKey = 'law_import_' . md5($url);
            if (!$dry && Cache::has($cacheKey)) {
                Log::info('Skipping recently imported law', [
                    'url' => $url,
                    'progress' => ($idx + 1) . '/' . $totalUrls,
                ]);
                $processed++;
                continue;
            }

            try {
                Log::info('Starting law import', [
                    'url' => $url,
                    'progress' => ($idx + 1) . '/' . $totalUrls,
                ]);

                $html = Http::retry(2, 250)->get($url)->throw()->body();
                $title = $options['title'] ?? $this->extractTitle($html) ?? 'Zakon (zakon.hr)';
                $pubDate = $options['date'] ?? ($this->extractPublishedDate($html) ?? null);
                $title = Str::replace(" -  Zakon.hr - ", '-', $title);
                $slug = Str::slug($title);
                $titleSnake = Str::snake($title);
                $dateDir = ($pubDate ?: date('Y-m-d'));
                $baseDir = 'hr-laws/zakonhr/'.$slug.'/'.$dateDir;
                Storage::put($baseDir.'/source.html', $html);

                // doc_id stable group identifier
                $docId = 'zakonhr-'.$slug.'-'.$dateDir;

                $articles = $this->parser->splitIntoArticles($html);
                if (empty($articles)) { $processed++; continue; }

                // Create or reuse parent IngestedLaw (skip in dry runs)
                $ingested = null;
                if (!$dry) {
                    $ingested = $this->ensureIngestedLaw($docId, $title, $url, [
                        'source' => 'zakon.hr',
                        'date_published' => $pubDate,
                        'slug' => $slug,
                    ]);
                }

                $docs = [];
                $articlePdfAbs = [];
                foreach ($articles as $idx => $art) {
                    $plain = $this->htmlToText($art['html'] ?? '');
                    if ($plain === '') continue;

                    $articleNumber = (string)($art['number'] ?? ($idx+1));
                    $docIdNew = $docId . '-clanak-'.$articleNumber;

                    // Render per-article PDF
                    $pdfFileName = $titleSnake.' - clanak-'.$articleNumber.'.pdf';
                    $pdfRelPath = $baseDir.'/'.$pdfFileName;
                    if (!$dry) {
                        $this->renderer->renderArticle([
                            'law_title' => $title,
                            'law_eli' => '',
                            'law_pub_date' => $pubDate ?: '',
                            'article_number' => $articleNumber,
                            'article_html' => $art['html'],
                            'generated_at' => gmdate('c'),
                            'generator_version' => '1.0.0',
                            'search_tags' => array_values(array_filter([$title, 'članak '.$articleNumber, $pubDate])),
                        ], Storage::path($pdfRelPath));
                        $this->recordLawUpload($ingested?->id, $docIdNew, $pdfRelPath, $pdfFileName, $url);
                        $articlePdfAbs[] = Storage::path($pdfRelPath);
                    }

                    $docs[] = [
                        'content' => $plain,
                        'metadata' => [
                            'source' => 'zakon.hr',
                            'url' => $url,
                            'title' => $title,
                            'date_published' => $pubDate,
                            'article_number' => $articleNumber,
                            'heading_chain' => $art['heading_chain'] ?? [],
                            'file_name' => $pdfFileName,
                        ],
                        'chunk_index' => 0,
                        'law_meta' => [
                            'title' => $title,
                            'jurisdiction' => 'HR',
                            'country' => 'HR',
                            'language' => 'hr',
                            'promulgation_date' => $pubDate,
                            'source_url' => $url,
                            'version' => 'as-published',
                            'tags' => $art['heading_chain'] ?? [],
                        ],
                    ];
                    $wouldChunks++;
                    $totalArticles++;
                }

                // Merge full PDF from article PDFs
                if (!$dry && !empty($articlePdfAbs)) {
                    $fullRel = $baseDir.'/full.pdf';
                    try {
                        $this->merger->merge($articlePdfAbs, Storage::path($fullRel));
                        $this->recordLawUpload($ingested?->id, $docId, $fullRel, $titleSnake.' - full.pdf', $url);
                    } catch (\Throwable $e) {
                        Log::warning('Failed merging full law PDF', ['url' => $url, 'err' => $e->getMessage()]);
                    }
                }

                if (!empty($docs) && !$dry) {
                    Log::info('Generating embeddings and inserting into vector store', [
                        'doc_id' => $docId,
                        'chunks' => count($docs),
                        'progress' => ($idx + 1) . '/' . $totalUrls,
                    ]);

                    $res = $this->vectorStore->ingest($docId, $docs, [
                        'model' => $model,
                        'provider' => 'openai',
                        'ingested_law_id' => $ingested?->id,
                    ]);
                    $totalInserted += (int)($res['inserted'] ?? 0);

                    // Cache this URL to prevent duplicate imports for 24 hours
                    Cache::put($cacheKey, now()->toIso8601String(), now()->addDay());

                    Log::info('Law import completed', [
                        'url' => $url,
                        'title' => $title,
                        'articles' => count($articles),
                        'inserted' => $res['inserted'] ?? 0,
                        'progress' => ($idx + 1) . '/' . $totalUrls,
                    ]);
                }
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('ZakonHR ingest failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return [
            'urls_processed' => $processed,
            'articles_seen' => $totalArticles,
            'inserted' => $totalInserted,
            'would_chunks' => $wouldChunks,
            'errors' => $errors,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    /**
     * Offline/inline HTML ingestion for validation or custom HTML sources.
     */
    public function ingestHtml(string $html, array $options = [], string $sourceUrl = 'offline://zakonhr-sample'): array
    {
        $model = $options['model'] ?? config('openai.models.embeddings');
        $dry = (bool)($options['dry'] ?? false);

        $title = $options['title'] ?? ($this->extractTitle($html) ?? 'Zakon (offline)');
        $title = Str::replace(" -  Zakon.hr", '', $title);
        $slug = Str::slug($title);
        $titleSnake = Str::snake($title);
        $datePub = $options['date'] ?? ($this->extractPublishedDate($html) ?? null);
        $dateDir = ($datePub ?: date('Y-m-d'));
        $baseDir = 'hr-laws/zakonhr/'.$slug.'/'.$dateDir;
        Storage::put($baseDir.'/source.html', $html);

        $docId = 'zakonhr-'.$slug.'-'.$dateDir;

        $articles = $this->parser->splitIntoArticles($html);
        $docs = [];
        $wouldChunks = 0; $totalArticles = 0; $totalInserted = 0;
        $articlePdfAbs = [];

        // Ensure IngestedLaw (skip when dry)
        $ingested = null;
        if (!$dry) {
            $ingested = $this->ensureIngestedLaw($docId, $title, $sourceUrl, [
                'source' => 'zakon.hr',
                'date_published' => $datePub,
                'slug' => $slug,
            ]);
        }

        foreach ($articles as $idx => $art) {
            $plain = $this->htmlToText($art['html'] ?? '');
            if ($plain === '') continue;
            $articleNumber = (string)($art['number'] ?? ($idx+1));

            if (!$dry) {
                $pdfFileName = $titleSnake.' - clanak-'.$articleNumber.'.pdf';
                $pdfRel = $baseDir.'/'.$pdfFileName;
                $this->renderer->renderArticle([
                    'law_title' => $title,
                    'law_eli' => '',
                    'law_pub_date' => $datePub ?: '',
                    'article_number' => $articleNumber,
                    'article_html' => $art['html'],
                    'generated_at' => gmdate('c'),
                    'generator_version' => '1.0.0',
                    'search_tags' => array_values(array_filter([$title, 'članak '.$articleNumber, $datePub])),
                ], Storage::path($pdfRel));
                $this->recordLawUpload($ingested?->id, $docId.'-clanak-'.$articleNumber, $pdfRel, $pdfFileName, $sourceUrl);
                $articlePdfAbs[] = Storage::path($pdfRel);
            }

            $docs[] = [
                'content' => $plain,
                'metadata' => [
                    'source' => 'zakon.hr',
                    'url' => $sourceUrl,
                    'title' => $title,
                    'date_published' => $datePub,
                    'article_number' => $articleNumber,
                    'heading_chain' => $art['heading_chain'] ?? [],
                    'file_name' => $pdfFileName,
                ],
                'chunk_index' => 0,
                'law_meta' => [
                    'title' => $title,
                    'jurisdiction' => 'HR',
                    'country' => 'HR',
                    'language' => 'hr',
                    'promulgation_date' => $datePub,
                    'source_url' => $sourceUrl,
                    'version' => 'offline',
                    'tags' => $art['heading_chain'] ?? [],
                ],
            ];
            $wouldChunks++;
            $totalArticles++;
        }

        if (!$dry && !empty($articlePdfAbs)) {
            $fullRel = $baseDir.'/full.pdf';
            try {
                $this->merger->merge($articlePdfAbs, Storage::path($fullRel));
                $this->recordLawUpload($ingested?->id, $docId, $fullRel, $titleSnake.' - full.pdf', $sourceUrl);
            } catch (\Throwable $e) {
                Log::warning('Failed merging full law PDF (offline)', ['err' => $e->getMessage()]);
            }
        }

        if (!empty($docs) && !$dry) {
            $res = $this->vectorStore->ingest($docId, $docs, [
                'model' => $model,
                'provider' => 'openai',
                'ingested_law_id' => $ingested?->id,
            ]);
            $totalInserted += (int)($res['inserted'] ?? 0);
        }

        return [
            'urls_processed' => 1,
            'articles_seen' => $totalArticles,
            'inserted' => $totalInserted,
            'would_chunks' => $wouldChunks,
            'errors' => 0,
            'model' => $model,
            'dry' => $dry,
        ];
    }

    protected function htmlToText(string $html): string
    {
        // Remove script/style blocks before stripping tags
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '');
        // Light cleanup of common noise patterns
        $text = preg_replace('/Copyright\s+©?\s+[^ ]+/', '', $text);
        return trim($text);
    }

    protected function extractPublishedDate(string $html): ?string
    {
        if (preg_match('/na\h+snazi\h+od\h*:?\h*(?:<[^>]>\h)*((?:0?[1-9]|[12]\d|3[01]).(?:0?[1-9]|1[0-2]).\d{4}).?/iu', $html, $m)) {
            $date = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $this->validateAndNormalizeDate($date);
        }

        return null;
    }

    /**
     * Validates and normalizes a date string to prevent strtotime issues
     * Returns normalized date in Y-m-d format or null if invalid
     */
    protected function validateAndNormalizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        // Try to parse the date
        $timestamp = @strtotime($date);

        // Validate timestamp - strtotime returns false on failure or negative timestamp
        // Also check it's not the Unix epoch (1970-01-01) which indicates parsing failure
        if ($timestamp === false || $timestamp < 0) {
            Log::warning('Invalid date format encountered', ['date' => $date]);
            return null;
        }

        // Ensure the year is reasonable (between 1900 and current year + 10)
        $year = (int) date('Y', $timestamp);
        $currentYear = (int) date('Y');
        if ($year < 1900 || $year > ($currentYear + 10)) {
            Log::warning('Date year out of reasonable range', ['date' => $date, 'year' => $year]);
            return null;
        }

        // Return normalized date in Y-m-d format
        return date('Y-m-d', $timestamp);
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return null;
    }

    protected function recordLawUpload(?string $ingestedLawId, string $docId, string $relPath, string $originalName, ?string $sourceUrl = null): void
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

    protected function ensureIngestedLaw(string $docId, string $title, ?string $sourceUrl = null, array $meta = []): IngestedLaw
    {
        $existing = IngestedLaw::query()
            ->where('doc_id', $docId)
            ->orWhere(function($q) use ($sourceUrl) { if ($sourceUrl) $q->where('source_url', $sourceUrl); })
            ->first();
        if ($existing) return $existing;

        return IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => $docId,
            'title' => (string) $title,
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => $sourceUrl,
            'aliases' => array_values(array_filter([$docId, $sourceUrl])),
            'keywords' => array_values(array_filter([$title, $meta['date_published'] ?? null, 'zakon.hr'])),
            'keywords_text' => implode(' ', array_values(array_filter([$title, $meta['date_published'] ?? null, 'zakon.hr']))),
            'metadata' => $meta,
            'ingested_at' => now(),
        ]);
    }
}
