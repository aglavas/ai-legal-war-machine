<?php

namespace App\Services;

use App\Models\AgentVectorMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class IngestPipelineService
{
    public function __construct(protected OpenAIService $openai, protected ?OcrService $ocr = null)
    {
    }

    public function ingestText(string $agent, string $namespace, string $text, array $options = []): array
    {
        $chunkChars = (int)($options['chunk_chars'] ?? 2000);
        $overlap = (int)($options['overlap'] ?? 200);
        $model = $options['model'] ?? config('openai.models.embeddings');

        $chunks = $this->chunkText($text, $chunkChars, $overlap);
        if (empty($chunks)) return ['count' => 0, 'inserted' => 0];

        // Map chunks to docs
        $docs = [];
        foreach ($chunks as $i => $content) {
            $docs[] = [
                'content' => $content,
                'metadata' => null,
                'source' => $options['source'] ?? 'text',
                'source_id' => $options['source_id'] ?? null,
                'chunk_index' => $i,
            ];
        }
        return $this->ingestDocuments($agent, $namespace, $docs, ['model' => $model]);
    }

    public function ingestFile(string $agent, string $namespace, string $path, ?string $mime = null, array $options = []): array
    {
        $mime = $mime ?: (function_exists('mime_content_type') ? @mime_content_type($path) : null);
        $content = '';
        if ($mime === 'application/pdf' || str_ends_with(strtolower($path), '.pdf')) {
            if ($this->ocr) {
                $content = $this->ocr->extractTextFromPdf($path) ?? '';
            }
        }
        if ($content === '') {
            $content = @file_get_contents($path) ?: '';
        }
        if ($content === '') {
            return ['count' => 0, 'inserted' => 0, 'skipped' => true];
        }
        return $this->digestAndStore($agent, $namespace, $content, [
            'source' => 'file',
            'source_id' => basename($path),
            'model' => $options['model'] ?? config('openai.models.embeddings'),
            'chunk_chars' => $options['chunk_chars'] ?? 2000,
            'overlap' => $options['overlap'] ?? 200,
        ]);
    }

    protected function digestAndStore(string $agent, string $namespace, string $text, array $opts): array
    {
        $chunkChars = (int)($opts['chunk_chars'] ?? 2000);
        $overlap = (int)($opts['overlap'] ?? 200);
        $model = $opts['model'] ?? config('openai.models.embeddings');
        $source = $opts['source'] ?? null;
        $sourceId = $opts['source_id'] ?? null;

        $chunks = $this->chunkText($text, $chunkChars, $overlap);
        if (empty($chunks)) return ['count' => 0, 'inserted' => 0];

        $docs = [];
        foreach ($chunks as $i => $content) {
            $docs[] = [
                'content' => $content,
                'metadata' => null,
                'source' => $source,
                'source_id' => $sourceId,
                'chunk_index' => $i,
            ];
        }
        return $this->ingestDocuments($agent, $namespace, $docs, ['model' => $model]);
    }

    /**
     * Batch-ingest pre-chunked documents (content + optional metadata).
     * Each doc: [content, metadata, source, source_id, chunk_index]
     */
    public function ingestDocuments(string $agent, string $namespace, array $docs, array $options = []): array
    {
        $docs = array_values(array_filter($docs, fn($d) => isset($d['content']) && trim((string)$d['content']) !== ''));
        if (empty($docs)) return ['count' => 0, 'inserted' => 0];

        $model = $options['model'] ?? config('openai.models.embeddings');
        $inputs = array_map(fn($d) => (string)$d['content'], $docs);
        $emb = $this->openai->embeddings($inputs, $model);
        $data = $emb['data'] ?? [];
        if (count($data) !== count($docs)) {
            Log::warning('Embedding count mismatch', ['docs' => count($docs), 'embeddings' => count($data)]);
        }
        $dims = isset($data[0]['embedding']) ? count($data[0]['embedding']) : null;

        $driver = DB::connection()->getDriverName();
        $table = (new AgentVectorMemory())->getTable();

        $inserted = 0;
        DB::transaction(function () use ($docs, $data, $agent, $namespace, $dims, $model, $driver, $table, &$inserted) {
            $now = now();
            foreach ($docs as $i => $doc) {
                $content = (string) $doc['content'];
                $vec = $data[$i]['embedding'] ?? null;
                if (!is_array($vec)) continue;
                $hash = hash('sha256', $content);
                $exists = AgentVectorMemory::where('agent_name', $agent)
                    ->where('content_hash', $hash)
                    ->exists();
                if ($exists) continue;

                $payload = [
                    'id' => (string) Str::ulid(),
                    'agent_name' => $agent,
                    'namespace' => $namespace,
                    'content' => $content,
                    'metadata' => isset($doc['metadata']) ? json_encode($doc['metadata']) : null,
                    'source' => $doc['source'] ?? null,
                    'source_id' => $doc['source_id'] ?? null,
                    'chunk_index' => (int)($doc['chunk_index'] ?? 0),
                    'embedding_provider' => 'openai',
                    'embedding_model' => $model,
                    'embedding_dimensions' => $dims ?? count($vec),
                    'embedding_norm' => $this->norm($vec),
                    'content_hash' => $hash,
                    'token_count' => $this->estimateTokens($content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($driver === 'pgsql') {
                    $payload['embedding'] = DB::raw($this->toPgVectorCastLiteral($vec));
                } else {
                    $payload['embedding_vector'] = json_encode($vec);
                }

                DB::table($table)->insert($payload);
                $inserted++;
            }
        });

        return ['count' => count($docs), 'inserted' => $inserted, 'dimensions' => $dims, 'model' => $model];
    }

    public function chunkText(string $text, int $chunkChars = 2000, int $overlap = 200): array
    {
        $text = trim($text);
        if ($text === '') return [];
        $chunks = [];
        $len = strlen($text);
        $start = 0;
        while ($start < $len) {
            $end = min($start + $chunkChars, $len);
            $slice = substr($text, $start, $end - $start);
            $chunks[] = $this->smartTrim($slice);
            if ($end >= $len) break;
            $start = max(0, $end - $overlap);
        }
        return $chunks;
    }

    protected function smartTrim(string $s): string
    {
        // try to avoid cutting in the middle of words
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s ?? '');
    }

    protected function estimateTokens(string $s): int
    {
        // Rough heuristic: ~4 chars per token
        return (int) ceil(strlen($s) / 4);
    }

    protected function norm(array $vec): float
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += ($v * $v);
        }
        return sqrt($sum);
    }

    protected function dot(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) $sum += ($a[$i] * $b[$i]);
        return $sum;
    }

    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) {
            $parts[] = rtrim(rtrim(number_format((float)$v, 8, '.', ''), '0'), '.');
        }
        return '[' . implode(',', $parts) . ']';
    }

    protected function toPgVectorCastLiteral(array $vec): string
    {
        // returns SQL snippet like '\'[0.1,0.2]\'::vector'
        return "'" . $this->toPgVectorLiteral($vec) . "'::vector";
    }

    public function search(string $agent, ?string $namespace, string $query, int $limit = 5): array
    {
        $embedRes = $this->openai->embeddings($query);
        $vec = $embedRes['data'][0]['embedding'] ?? null;
        if (!is_array($vec)) return ['results' => [], 'count' => 0];
        $qNorm = $this->norm($vec);
        if ($qNorm <= 0) return ['results' => [], 'count' => 0];

        $driver = DB::connection()->getDriverName();
        $table = (new AgentVectorMemory())->getTable();

        if ($driver === 'pgsql') {
            $vecLiteral = $this->toPgVectorCastLiteral($vec);
            $bindings = [$agent];
            $where = 'agent_name = ?';
            if ($namespace) { $where .= ' AND namespace = ?'; $bindings[] = $namespace; }

            $sql = "SELECT id, content, source, source_id, chunk_index, (1 - (embedding <=> {$vecLiteral})) AS score
                    FROM {$table}
                    WHERE {$where}
                    ORDER BY embedding <=> {$vecLiteral}
                    LIMIT ".(int) max(1, $limit);

            $rows = DB::select($sql, $bindings);
            $results = array_map(function ($r) {
                return [
                    'id' => $r->id,
                    'content' => $r->content,
                    'score' => (float) $r->score,
                    'source' => $r->source,
                    'source_id' => $r->source_id,
                    'chunk_index' => (int) $r->chunk_index,
                ];
            }, $rows);

            return ['results' => $results, 'count' => count($results)];
        }

        // Fallback: compute in PHP using JSON vectors
        $q = AgentVectorMemory::query()->where('agent_name', $agent);
        if ($namespace) $q->where('namespace', $namespace);
        $rows = $q->limit(500)->get(['id','content','embedding_vector','embedding_norm','source','source_id','chunk_index']);

        $scored = [];
        foreach ($rows as $r) {
            $v = $r->embedding_vector ?? null;
            $rNorm = (float) ($r->embedding_norm ?? 0);
            if (!is_array($v) || $rNorm <= 0) continue;
            $dot = $this->dot($vec, $v);
            $cos = $dot / ($qNorm * $rNorm);
            $scored[] = [
                'id' => $r->id,
                'content' => $r->content,
                'score' => $cos,
                'source' => $r->source,
                'source_id' => $r->source_id,
                'chunk_index' => $r->chunk_index,
            ];
        }
        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(1, $limit));

        return [
            'results' => $top,
            'count' => count($top),
        ];
    }
}
