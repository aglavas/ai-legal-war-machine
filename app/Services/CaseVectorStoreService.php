<?php

namespace App\Services;

use App\Models\CaseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaseVectorStoreService
{
    public function __construct(
        protected OpenAIService $openai,
        protected ?GraphRagService $graphRag = null
    ) {
    }

    /**
     * @param string $caseId ULID of the LegalCase
     * @param string $docId Group identifier for this document within the case (e.g., decision ID or ECLI)
     * @param array<int,array{content:string,metadata?:array,chunk_index?:int}> $docs
     * @param array $options model, provider, upload_id
     */
    public function ingest(string $caseId, string $docId, array $docs, array $options = []): array
    {
        $docs = array_values(array_filter($docs, fn($d) => isset($d['content']) && trim((string)$d['content']) !== ''));
        if (empty($docs)) return ['count' => 0, 'inserted' => 0];

        $model = $options['model'] ?? config('openai.models.embeddings');
        $provider = $options['provider'] ?? 'openai';
        $uploadId = $options['upload_id'] ?? null;

        $inputs = array_map(fn($d) => (string)$d['content'], $docs);
        $emb = $this->openai->embeddings($inputs, $model);
        $data = $emb['data'] ?? [];
        if (count($data) !== count($docs)) {
            Log::warning('Embedding count mismatch (cases)', ['docs' => count($docs), 'embeddings' => count($data)]);
        }
        $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

        $driver = DB::connection()->getDriverName();
        $table = (new CaseDocument())->getTable();

        $inserted = 0;
        $insertedIds = [];
        DB::transaction(function () use ($docs, $data, $caseId, $docId, $dims, $model, $provider, $driver, $table, $uploadId, &$inserted, &$insertedIds) {
            $now = now();
            foreach ($docs as $i => $doc) {
                $content = (string)$doc['content'];
                $vec = $data[$i]['embedding'] ?? null;
                if (!is_array($vec)) continue;
                $hash = hash('sha256', $content);

                $exists = DB::table($table)
                    ->where('case_id', $caseId)
                    ->where('content_hash', $hash)
                    ->exists();
                if ($exists) continue;

                $caseDocId = (string) Str::ulid();
                $payload = [
                    'id' => $caseDocId,
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'upload_id' => $uploadId,
                    'content' => $content,
                    'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata']) : null,
                    'source' => $doc['source'] ?? null,
                    'source_id' => $doc['source_id'] ?? null,
                    'chunk_index' => (int)($doc['chunk_index'] ?? 0),
                    'embedding_provider' => $provider,
                    'embedding_model' => $model,
                    'embedding_dimensions' => $dims ?? count($vec),
                    'embedding_norm' => $this->norm($vec),
                    'content_hash' => $hash,
                    'token_count' => $this->estimateTokens($content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $payload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vec));
//                if ($driver === 'pgsql') {
//
//                } else {
//                    $payload['embedding_vector'] = json_encode($vec);
//                }

                DB::table($table)->insert($payload);
                $inserted++;
                $insertedIds[] = $caseDocId;
            }
        });

        // Sync to graph database if enabled
        if ($this->graphRag && config('neo4j.sync.auto_sync', true) && config('neo4j.sync.enabled', true)) {
            foreach ($insertedIds as $caseDocId) {
                try {
                    $this->graphRag->syncCase($caseDocId);
                } catch (\Exception $e) {
                    Log::warning('Failed to sync case to graph database', [
                        'case_doc_id' => $caseDocId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['count' => count($docs), 'inserted' => $inserted, 'dimensions' => $dims, 'model' => $model];
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
