<?php

namespace App\Console\Commands;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocumentUpload;
use App\Models\EoglasnaNotice;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\GraphRagService;
use App\Services\HrLegalCitationsDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IngestCourtDecisionsEmbeddings extends Command
{
    protected $signature = 'decisions:ingest
        {--source=eoglasna : Data source (eoglasna, uploads)}
        {--limit= : Max decisions to process}
        {--model= : Embedding model}
        {--chunk=1200 : Chunk size in chars}
        {--overlap=150 : Overlap in chars}
        {--sync-graph : Sync to graph database}';

    protected $description = 'Ingest court decisions, extract metadata, chunk, embed and store in vector database';

    public function __construct(
        protected CourtDecisionVectorStoreService $vectorStore,
        protected HrLegalCitationsDetector $citationDetector,
        protected ?GraphRagService $graphRag = null
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->option('source') ?? 'eoglasna';
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $model = $this->option('model') ?: config('openai.models.embeddings');
        $chunkSize = (int) ($this->option('chunk') ?? 1200);
        $overlap = (int) ($this->option('overlap') ?? 150);
        $syncGraph = $this->option('sync-graph');

        $this->info("Starting court decisions ingestion from source: {$source}");

        $stats = match ($source) {
            'eoglasna' => $this->ingestFromEoglasna($limit, $model, $chunkSize, $overlap, $syncGraph),
            'uploads' => $this->ingestFromUploads($limit, $model, $chunkSize, $overlap, $syncGraph),
            default => throw new \InvalidArgumentException("Unknown source: {$source}"),
        };

        $this->info('Ingestion completed:');
        $this->line("  Decisions processed: {$stats['decisions_processed']}");
        $this->line("  Documents inserted: {$stats['documents_inserted']}");
        $this->line("  Errors: {$stats['errors']}");
        $this->line("  Model: {$stats['model']}");

        if ($syncGraph && isset($stats['synced_to_graph'])) {
            $this->line("  Synced to graph: {$stats['synced_to_graph']}");
        }

        return self::SUCCESS;
    }

    protected function ingestFromEoglasna(
        ?int $limit,
        string $model,
        int $chunkSize,
        int $overlap,
        bool $syncGraph
    ): array {
        $decisionsProcessed = 0;
        $documentsInserted = 0;
        $errors = 0;
        $syncedToGraph = 0;

        // Query EOGLASNA notices that represent court decisions
        $query = EoglasnaNotice::query()
            ->whereNotNull('case_number')
            ->whereNotNull('notice_documents_download_url')
            ->orderBy('date_published', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $notice) {
            try {
                $decisionsProcessed++;

                // Parse metadata from EOGLASNA notice
                $metadata = $this->parseEoglasnaMetadata($notice);

                // Check if already ingested
                $existing = CourtDecision::where('ecli', $metadata['ecli'])
                    ->orWhere('case_number', $metadata['case_number'])
                    ->first();

                if ($existing) {
                    $this->line("  Skipping already ingested: {$metadata['case_number']}");
                    continue;
                }

                // Create parent CourtDecision record
                $decisionId = (string) Str::ulid();
                $decision = CourtDecision::create([
                    'id' => $decisionId,
                    'case_number' => $metadata['case_number'],
                    'title' => $metadata['title'],
                    'court' => $metadata['court'],
                    'jurisdiction' => 'HR',
                    'judge' => $metadata['judge'],
                    'decision_date' => $metadata['decision_date'],
                    'publication_date' => $metadata['publication_date'],
                    'decision_type' => $metadata['decision_type'],
                    'ecli' => $metadata['ecli'],
                    'tags' => $metadata['tags'],
                    'description' => $metadata['description'],
                ]);

                // Download and process decision documents
                $docId = $metadata['ecli'] ?? $metadata['case_number'];
                $documents = $this->fetchDecisionDocuments($notice, $decisionId, $docId);

                if (empty($documents)) {
                    $this->warn("  No documents for {$metadata['case_number']}");
                    continue;
                }

                // Chunk the documents
                $chunks = $this->chunkDocuments($documents, $chunkSize, $overlap, $metadata);

                // Ingest into vector store
                if (!empty($chunks)) {
                    $result = $this->vectorStore->ingest($decisionId, $docId, $chunks, [
                        'model' => $model,
                        'provider' => 'openai',
                    ]);

                    $documentsInserted += $result['inserted'] ?? 0;
                    $this->info("  Processed {$metadata['case_number']}: {$result['inserted']} chunks");
                }

                // Sync to graph database if requested
                if ($syncGraph && $this->graphRag && method_exists($this->graphRag, 'syncCourtDecision')) {
                    try {
                        $this->graphRag->syncCourtDecision($decisionId);
                        $syncedToGraph++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to sync decision to graph', [
                            'decision_id' => $decisionId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Throwable $e) {
                $errors++;
                Log::error('Court decision ingestion failed', [
                    'notice_uuid' => $notice->uuid ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  Error processing notice: {$e->getMessage()}");
            }
        }

        return [
            'decisions_processed' => $decisionsProcessed,
            'documents_inserted' => $documentsInserted,
            'errors' => $errors,
            'synced_to_graph' => $syncedToGraph,
            'model' => $model,
        ];
    }

    protected function ingestFromUploads(
        ?int $limit,
        string $model,
        int $chunkSize,
        int $overlap,
        bool $syncGraph
    ): array {
        // TODO: Implement ingestion from uploaded decision files
        $this->warn('Ingestion from uploads not yet implemented');

        return [
            'decisions_processed' => 0,
            'documents_inserted' => 0,
            'errors' => 0,
            'synced_to_graph' => 0,
            'model' => $model,
        ];
    }

    protected function parseEoglasnaMetadata(EoglasnaNotice $notice): array
    {
        $details = $notice->court_notice_details ?? [];

        // Extract ECLI if available
        $ecli = null;
        if (!empty($details['ecli'])) {
            $ecli = $details['ecli'];
        } else {
            // Try to generate ECLI from case number and court
            $ecli = $this->generateEcli($notice);
        }

        // Parse decision type from notice type or title
        $decisionType = $this->parseDecisionType($notice->notice_type, $notice->title);

        // Extract judge/panel information
        $judge = null;
        if (!empty($details['judge'])) {
            $judge = $details['judge'];
        } elseif (!empty($details['panel'])) {
            $judge = 'Panel: ' . $details['panel'];
        }

        // Extract chamber
        $chamber = $details['chamber'] ?? null;

        return [
            'case_number' => $notice->case_number,
            'title' => $notice->title ?? "Case {$notice->case_number}",
            'court' => $notice->court_name,
            'court_code' => $notice->court_code,
            'court_type' => $notice->court_type,
            'chamber' => $chamber,
            'judge' => $judge,
            'decision_date' => $notice->date_published,
            'publication_date' => $notice->date_published,
            'decision_type' => $decisionType,
            'ecli' => $ecli,
            'tags' => array_filter([
                $notice->case_type,
                $notice->court_type,
                $decisionType,
            ]),
            'description' => $notice->title,
        ];
    }

    protected function generateEcli(EoglasnaNotice $notice): ?string
    {
        // ECLI format: ECLI:HR:COURT:YEAR:NUMBER
        // Example: ECLI:HR:VSR:2023:123

        if (!$notice->case_number || !$notice->date_published) {
            return null;
        }

        // Extract court code from court name or use placeholder
        $courtCode = $notice->court_code ?? 'COURT';

        // Extract year from publication date
        $year = $notice->date_published->format('Y');

        // Clean case number to get numeric part
        $number = preg_replace('/[^0-9]/', '', $notice->case_number);

        return "ECLI:HR:{$courtCode}:{$year}:{$number}";
    }

    protected function parseDecisionType(string $noticeType, ?string $title): string
    {
        $lowerTitle = mb_strtolower($title ?? '');

        if (mb_stripos($lowerTitle, 'presuda') !== false) {
            return 'judgment';
        } elseif (mb_stripos($lowerTitle, 'rješenje') !== false) {
            return 'decision';
        } elseif (mb_stripos($lowerTitle, 'zaključak') !== false) {
            return 'conclusion';
        } elseif (mb_stripos($lowerTitle, 'nalog') !== false) {
            return 'order';
        }

        return 'other';
    }

    protected function fetchDecisionDocuments(
        EoglasnaNotice $notice,
        string $decisionId,
        string $docId
    ): array {
        $documents = [];

        // If notice has document download URL, fetch it
        if ($notice->notice_documents_download_url) {
            try {
                $response = Http::timeout(30)->get($notice->notice_documents_download_url);

                if ($response->successful()) {
                    $content = $response->body();
                    $mimeType = $response->header('Content-Type');

                    // Store the document
                    $baseDir = sprintf('court-decisions/%s', $decisionId);
                    $fileName = 'decision-' . ($notice->uuid ?? Str::random(8)) . $this->getExtensionFromMime($mimeType);
                    $filePath = $baseDir . '/' . $fileName;

                    Storage::put($filePath, $content);

                    // Record upload
                    $this->recordDecisionUpload(
                        $decisionId,
                        $docId,
                        $filePath,
                        $fileName,
                        $notice->notice_documents_download_url,
                        $mimeType
                    );

                    // Extract text from document
                    $text = $this->extractTextFromDocument($content, $mimeType);

                    if ($text) {
                        $documents[] = [
                            'content' => $text,
                            'source' => 'eoglasna',
                            'source_id' => $notice->uuid,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch decision document', [
                    'url' => $notice->notice_documents_download_url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: use notice title and case information as minimal document
        if (empty($documents) && $notice->title) {
            $text = implode("\n\n", array_filter([
                $notice->title,
                "Case Number: {$notice->case_number}",
                "Court: {$notice->court_name}",
                "Date: {$notice->date_published?->format('Y-m-d')}",
                "Type: {$notice->case_type}",
            ]));

            $documents[] = [
                'content' => $text,
                'source' => 'eoglasna',
                'source_id' => $notice->uuid,
            ];
        }

        return $documents;
    }

    protected function extractTextFromDocument(string $content, ?string $mimeType): ?string
    {
        // For now, handle basic text formats
        // TODO: Integrate with Textract or other document parsing services

        if (str_contains($mimeType ?? '', 'text/plain')) {
            return $content;
        }

        if (str_contains($mimeType ?? '', 'text/html')) {
            // Strip HTML tags
            $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return preg_replace('/\s+/u', ' ', $text ?? '');
        }

        // For PDF, DOCX, etc., return null (needs specialized processing)
        return null;
    }

    protected function getExtensionFromMime(?string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => '.pdf',
            'text/html' => '.html',
            'text/plain' => '.txt',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            default => '.bin',
        };
    }

    protected function chunkDocuments(
        array $documents,
        int $chunkSize,
        int $overlap,
        array $metadata
    ): array {
        $chunks = [];
        $chunkIndex = 0;

        foreach ($documents as $doc) {
            $content = $doc['content'] ?? '';
            $textChunks = $this->splitIntoChunks($content, $chunkSize, $overlap);

            foreach ($textChunks as $chunk) {
                $chunks[] = [
                    'content' => $chunk,
                    'metadata' => array_merge($metadata, [
                        'chunk_index' => $chunkIndex,
                        'source' => $doc['source'] ?? null,
                        'source_id' => $doc['source_id'] ?? null,
                    ]),
                    'chunk_index' => $chunkIndex,
                    'source' => $doc['source'] ?? null,
                    'source_id' => $doc['source_id'] ?? null,
                ];
                $chunkIndex++;
            }
        }

        return $chunks;
    }

    protected function splitIntoChunks(string $text, int $chunkSize, int $overlap): array
    {
        $text = trim($text);
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $textLen = mb_strlen($text);

        while ($start < $textLen) {
            $end = min($start + $chunkSize, $textLen);

            // Try to break at sentence boundary
            if ($end < $textLen) {
                $lastPeriod = mb_strrpos(mb_substr($text, $start, $chunkSize), '.');
                $lastQuestion = mb_strrpos(mb_substr($text, $start, $chunkSize), '?');
                $lastExclaim = mb_strrpos(mb_substr($text, $start, $chunkSize), '!');

                $boundary = max($lastPeriod, $lastQuestion, $lastExclaim);
                if ($boundary !== false && $boundary > $chunkSize * 0.7) {
                    $end = $start + $boundary + 1;
                }
            }

            $chunk = mb_substr($text, $start, $end - $start);
            $chunks[] = trim($chunk);

            $start = $end - $overlap;
        }

        return array_filter($chunks, fn($c) => trim($c) !== '');
    }

    protected function recordDecisionUpload(
        string $decisionId,
        string $docId,
        string $relPath,
        string $originalName,
        ?string $sourceUrl,
        ?string $mimeType
    ): void {
        $abs = Storage::path($relPath);
        $size = is_file($abs) ? @filesize($abs) : null;
        $sha = is_file($abs) ? @hash_file('sha256', $abs) : null;

        CourtDecisionDocumentUpload::create([
            'id' => (string) Str::ulid(),
            'decision_id' => $decisionId,
            'doc_id' => $docId,
            'disk' => 'local',
            'local_path' => $relPath,
            'original_filename' => $originalName,
            'mime_type' => $mimeType ?? 'application/octet-stream',
            'file_size' => $size,
            'sha256' => $sha,
            'source_url' => $sourceUrl,
            'uploaded_at' => now(),
            'status' => 'stored',
        ]);
    }
}
