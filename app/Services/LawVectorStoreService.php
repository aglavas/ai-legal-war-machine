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
        $emb = $this->openai->embeddings($inputs, $model);
        $data = $emb['data'] ?? [];
        if (count($data) !== count($docs)) {
            Log::warning('Embedding count mismatch (laws)', ['docs' => count($docs), 'embeddings' => count($data)]);
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
        foreach ([
                     'title','law_number','jurisdiction','country','language','version','chapter','section','source_url'
                 ] as $key) {
            if (isset($meta[$key])) $map[$key] = $meta[$key];
        }
        foreach ([
                     'promulgation_date','effective_date','repeal_date'
                 ] as $dateKey) {
            if (!empty($meta[$dateKey])) $map[$dateKey] = $meta[$dateKey];
        }
        if (isset($meta['tags'])) $map['tags'] = json_encode((array)$meta['tags'], JSON_UNESCAPED_UNICODE);
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
}
