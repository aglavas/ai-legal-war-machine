<?php

namespace App\Services;

use App\Models\Law;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LawVectorStoreService
{
    public function __construct(
        protected OpenAIService $openai,
        protected ?GraphRagService $graphRag = null
    ) {
    }

    /**
     * Ingest pre-chunked law articles into laws table.
     * @param string $docId Stable identifier grouping chunks of the same law (e.g., ELI or slug-date)
     * @param array<int,array{content:string,metadata?:array,chunk_index?:int, law_meta?:array}> $docs
     * @param array $options model, provider, base_meta (title, law_number, jurisdiction, ...), ingested_law_id
     */
    public function ingest(string $docId, array $docs, array $options = []): array
    {
        $docs = array_values(array_filter($docs, fn($d) => isset($d['content']) && trim((string)$d['content']) !== ''));
        if (empty($docs)) return ['count' => 0, 'inserted' => 0];

        $model = $options['model'] ?? config('openai.models.embeddings');
        $provider = $options['provider'] ?? 'openai';
        $baseMeta = (array)($options['base_meta'] ?? []);
        $ingestedId = $options['ingested_law_id'] ?? null;

        $inputs = array_map(fn($d) => (string)$d['content'], $docs);
        $emb = $this->callEmbeddingsWithRetry($inputs, $model, $docId);
        $data = $emb['data'] ?? [];
        if (count($data) !== count($docs)) {
            Log::warning('Embedding count mismatch (laws)', [
                'docs' => count($docs),
                'embeddings' => count($data),
                'doc_id' => $docId,
            ]);
        }
        $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

        $driver = DB::connection()->getDriverName();
        $table = (new Law())->getTable();

        $inserted = 0;
        $insertedIds = [];
        DB::transaction(function () use ($docs, $data, $docId, $dims, $model, $provider, $driver, $table, $baseMeta, $ingestedId, &$inserted, &$insertedIds) {
            $now = now();
            foreach ($docs as $i => $doc) {
                $content = (string)$doc['content'];
                $vec = $data[$i]['embedding'] ?? null;
                if (!is_array($vec)) continue;
                $hash = hash('sha256', $content);

                $exists = DB::table($table)
                    ->where('doc_id', $docId)
                    ->where('content_hash', $hash)
                    ->exists();
                if ($exists) continue;

                $rowMeta = (array)($doc['law_meta'] ?? []);

                $lawId = (string) Str::ulid();
                $payload = array_merge([
                    'id' => $lawId,
                    'doc_id' => $docId,
                    'ingested_law_id' => $ingestedId,
                    // content
                    'content' => $content,
                    'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata'], JSON_UNESCAPED_UNICODE) : null,
                    'chunk_index' => (int)($doc['chunk_index'] ?? 0),
                    // embedding
                    'embedding_provider' => $provider,
                    'embedding_model' => $model,
                    'embedding_dimensions' => $dims ?? count($vec),
                    'embedding_norm' => $this->norm($vec),
                    'content_hash' => $hash,
                    'token_count' => $this->estimateTokens($content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $this->mapLawColumns(array_merge($baseMeta, $rowMeta)));

                if ($driver === 'pgsql') {
                    $payload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vec));
                } else {
                    $payload['embedding_vector'] = json_encode($vec);
                }

                DB::table($table)->insert($payload);
                $inserted++;
                $insertedIds[] = $lawId;
            }
        });

        // Sync to graph database if enabled
        if ($this->graphRag && config('neo4j.sync.auto_sync', true) && config('neo4j.sync.enabled', true)) {
            foreach ($insertedIds as $lawId) {
                try {
                    $this->graphRag->syncLaw($lawId);
                } catch (\Exception $e) {
                    Log::warning('Failed to sync law to graph database', [
                        'law_id' => $lawId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['count' => count($docs), 'inserted' => $inserted, 'dimensions' => $dims, 'model' => $model];
    }

    protected function mapLawColumns(array $meta): array
    {
        // Only map known scalar columns; leave the rest inside metadata JSON
        $map = [];

        // Map scalar text columns
        foreach ([
                     'title','law_number','jurisdiction','country','language','version','chapter','section','source_url'
                 ] as $key) {
            if (isset($meta[$key])) {
                $map[$key] = $meta[$key];
            }
        }

        // Map date columns - ensure they're present when supplied
        // Use null coalescing to handle empty strings
        foreach ([
                     'promulgation_date','effective_date','repeal_date'
                 ] as $dateKey) {
            if (!empty($meta[$dateKey])) {
                $map[$dateKey] = $meta[$dateKey];
            }
        }

        // Ensure tags are always stored as JSON array
        if (isset($meta['tags'])) {
            $tags = is_array($meta['tags']) ? $meta['tags'] : [$meta['tags']];
            $map['tags'] = json_encode(array_values(array_filter($tags)), JSON_UNESCAPED_UNICODE);
        }

        return $map;
    }

    protected function estimateTokens(string $s): int
    {
        return (int) ceil(strlen($s) / 4);
    }

    protected function norm(array $vec): float
    {
        $sum = 0.0;
        foreach ($vec as $v) $sum += ($v * $v);
        return sqrt($sum);
    }

    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) $parts[] = rtrim(rtrim(number_format((float)$v, 8, '.', ''), '0'), '.');
        return '[' . implode(',', $parts) . ']';
    }

    protected function toPgVectorCastLiteral(array $vec): string
    {
        return "'" . $this->toPgVectorLiteral($vec) . "'::vector";
    }

    /**
     * Call embeddings API with exponential backoff and jitter
     *
     * Implements robust retry logic for embedding generation:
     * - Tracks API call duration for performance monitoring
     * - Exponential backoff with jitter to handle rate limits
     * - Detailed logging of retries and failures
     * - Preserves error context for debugging
     *
     * @param array $inputs Array of text inputs to embed
     * @param string $model Embedding model to use
     * @param string $docId Document ID for logging context
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @return array Embeddings response from API
     * @throws \Exception If all retries are exhausted
     */
    protected function callEmbeddingsWithRetry(array $inputs, string $model, string $docId, int $maxRetries = 3): array
    {
        $attempt = 0;
        $lastException = null;
        $inputCount = count($inputs);

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                $startTime = microtime(true);
                $result = $this->openai->embeddings($inputs, $model);
                $duration = microtime(true) - $startTime;

                if ($attempt > 1) {
                    Log::info('Embeddings call succeeded after retry', [
                        'doc_id' => $docId,
                        'attempt' => $attempt,
                        'input_count' => $inputCount,
                        'model' => $model,
                        'duration_seconds' => round($duration, 3),
                    ]);
                }

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning('Embeddings call failed', [
                    'doc_id' => $docId,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'input_count' => $inputCount,
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);

                // Don't sleep after the last attempt
                if ($attempt < $maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    Log::debug('Retrying embeddings call after delay', [
                        'doc_id' => $docId,
                        'delay_ms' => $delay,
                        'next_attempt' => $attempt + 1,
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        // All retries exhausted
        Log::error('Embeddings call failed after all retries', [
            'doc_id' => $docId,
            'total_attempts' => $attempt,
            'input_count' => $inputCount,
            'model' => $model,
            'error' => $lastException->getMessage(),
        ]);

        throw new \Exception(
            "Failed to generate embeddings after {$maxRetries} attempts for doc_id: {$docId}",
            0,
            $lastException
        );
    }

    /**
     * Calculate exponential backoff delay with jitter
     *
     * Uses configurable base delay and jitter percentage.
     * Configuration keys:
     * - services.embeddings.retry_base_delay (default: 1000ms)
     * - services.embeddings.retry_jitter_percent (default: 0.5 = 50%)
     *
     * @param int $attempt Attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected function calculateBackoffDelay(int $attempt): int
    {
        // Exponential backoff: base_delay * 2^(attempt-1)
        $baseDelay = config('services.embeddings.retry_base_delay', 1000); // 1 second default
        $exponentialDelay = $baseDelay * pow(2, $attempt - 1);

        // Add jitter: random value between 0 and configured percentage of the delay
        $jitterPercent = config('services.embeddings.retry_jitter_percent', 0.5);
        $jitter = rand(0, (int)($exponentialDelay * $jitterPercent));

        return (int)($exponentialDelay + $jitter);
    }
}
