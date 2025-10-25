<?php

namespace App\Services;

use App\Models\AgentVectorMemory;
use App\Models\Law;
use App\Models\CaseDocument;
use App\Models\CourtDecisionDocument;
use App\Models\IngestedLaw;
use App\Models\CourtDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * AgentToolbox provides a comprehensive suite of tools for autonomous agents
 * to research legal topics, query databases, and save insights.
 */
class AgentToolbox
{
    public function __construct(
        protected OpenAIService $openai,
        protected GraphDatabaseService $graph
    ) {
    }

    /**
     * Vector search across laws, cases, and court decisions
     *
     * @param string $query The search query
     * @param array $options Search options:
     *   - types: array of 'laws', 'cases', 'decisions' (default: all)
     *   - limit: max results per type (default: 10)
     *   - jurisdiction: filter by jurisdiction
     *   - min_similarity: minimum cosine similarity (default: 0.7)
     * @return array Search results grouped by type
     */
    public function vectorSearch(string $query, array $options = []): array
    {
        $types = $options['types'] ?? ['laws', 'cases', 'decisions'];
        $limit = (int)($options['limit'] ?? 10);
        $jurisdiction = $options['jurisdiction'] ?? null;
        $minSimilarity = (float)($options['min_similarity'] ?? 0.7);

        // Generate embedding for query
        $model = config('openai.models.embeddings');
        $embedding = $this->openai->embeddings([$query], $model);
        $queryVector = $embedding['data'][0]['embedding'] ?? null;

        if (!$queryVector) {
            return ['error' => 'Failed to generate query embedding'];
        }

        $results = [];

        // Search laws
        if (in_array('laws', $types)) {
            $results['laws'] = $this->searchLaws($queryVector, $limit, $jurisdiction, $minSimilarity);
        }

        // Search cases
        if (in_array('cases', $types)) {
            $results['cases'] = $this->searchCases($queryVector, $limit, $jurisdiction, $minSimilarity);
        }

        // Search court decisions
        if (in_array('decisions', $types)) {
            $results['decisions'] = $this->searchDecisions($queryVector, $limit, $jurisdiction, $minSimilarity);
        }

        return $results;
    }

    /**
     * Search laws by vector similarity
     */
    protected function searchLaws(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('laws')
            ->select([
                'id',
                'doc_id',
                'title',
                'law_number',
                'jurisdiction',
                'content',
                'chunk_index',
                'metadata',
                'effective_date',
                'promulgation_date'
            ]);

        if ($jurisdiction) {
            $query->where('jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            // Fallback for non-PostgreSQL databases
            $query->whereNotNull('embedding_vector')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($law) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $lawVector = json_decode($law->embedding_vector ?? '[]', true);
                $law->similarity = $this->cosineSimilarity($queryVector, $lawVector);
            }
            $law->metadata = json_decode($law->metadata ?? '{}', true);
            return $law;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Search case documents by vector similarity
     */
    protected function searchCases(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('cases_documents')
            ->select([
                'cases_documents.id',
                'cases_documents.case_id',
                'cases_documents.doc_id',
                'cases_documents.title',
                'cases_documents.content',
                'cases_documents.chunk_index',
                'cases_documents.metadata',
                'cases.case_number',
                'cases.jurisdiction',
                'cases.court',
                'cases.status'
            ])
            ->leftJoin('cases', 'cases_documents.case_id', '=', 'cases.id');

        if ($jurisdiction) {
            $query->where('cases.jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (cases_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (cases_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("cases_documents.embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            $query->whereNotNull('cases_documents.embedding_vector')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($case) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $caseVector = json_decode($case->embedding_vector ?? '[]', true);
                $case->similarity = $this->cosineSimilarity($queryVector, $caseVector);
            }
            $case->metadata = json_decode($case->metadata ?? '{}', true);
            return $case;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Search court decisions by vector similarity
     */
    protected function searchDecisions(array $queryVector, int $limit, ?string $jurisdiction, float $minSimilarity): array
    {
        $driver = DB::connection()->getDriverName();
        $query = DB::table('court_decision_documents')
            ->select([
                'court_decision_documents.id',
                'court_decision_documents.decision_id',
                'court_decision_documents.doc_id',
                'court_decision_documents.title',
                'court_decision_documents.content',
                'court_decision_documents.chunk_index',
                'court_decision_documents.metadata',
                'court_decisions.case_number',
                'court_decisions.court',
                'court_decisions.jurisdiction',
                'court_decisions.decision_date',
                'court_decisions.decision_type'
            ])
            ->leftJoin('court_decisions', 'court_decision_documents.decision_id', '=', 'court_decisions.id');

        if ($jurisdiction) {
            $query->where('court_decisions.jurisdiction', $jurisdiction);
        }

        if ($driver === 'pgsql') {
            $vectorLiteral = $this->toPgVectorLiteral($queryVector);
            $query->selectRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->whereRaw("1 - (court_decision_documents.embedding <=> '{$vectorLiteral}'::vector) >= ?", [$minSimilarity])
                ->orderByRaw("court_decision_documents.embedding <=> '{$vectorLiteral}'::vector")
                ->limit($limit);
        } else {
            $query->whereNotNull('court_decision_documents.embedding_vector')->limit($limit * 3);
        }

        $results = $query->get()->map(function ($decision) use ($queryVector, $driver) {
            if ($driver !== 'pgsql') {
                $decisionVector = json_decode($decision->embedding_vector ?? '[]', true);
                $decision->similarity = $this->cosineSimilarity($queryVector, $decisionVector);
            }
            $decision->metadata = json_decode($decision->metadata ?? '{}', true);
            return $decision;
        });

        if ($driver !== 'pgsql') {
            $results = $results->filter(fn($r) => $r->similarity >= $minSimilarity)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();
        }

        return $results->toArray();
    }

    /**
     * Look up a specific law by law number and/or jurisdiction
     *
     * @param string $lawNumber The law number (e.g., "NN 94/14")
     * @param string|null $jurisdiction Optional jurisdiction filter
     * @return array Law information including all chunks
     */
    public function lawLookup(string $lawNumber, ?string $jurisdiction = null): array
    {
        $query = IngestedLaw::with('laws')
            ->where('law_number', 'LIKE', "%{$lawNumber}%");

        if ($jurisdiction) {
            $query->where('jurisdiction', $jurisdiction);
        }

        $law = $query->first();

        if (!$law) {
            return ['error' => 'Law not found', 'law_number' => $lawNumber, 'jurisdiction' => $jurisdiction];
        }

        return [
            'doc_id' => $law->doc_id,
            'title' => $law->title,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'keywords' => $law->keywords,
            'aliases' => $law->aliases,
            'metadata' => $law->metadata,
            'chunks' => $law->laws->map(fn($chunk) => [
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'chapter' => $chunk->chapter,
                'section' => $chunk->section,
                'tags' => $chunk->tags,
                'metadata' => $chunk->metadata,
            ])->toArray(),
        ];
    }

    /**
     * Look up court decisions by case number, court, or date range
     *
     * @param array $criteria Search criteria:
     *   - case_number: exact or partial match
     *   - court: court name
     *   - jurisdiction: jurisdiction
     *   - from_date: decision date from (Y-m-d)
     *   - to_date: decision date to (Y-m-d)
     *   - decision_type: type of decision
     *   - limit: max results (default: 20)
     * @return array Court decisions matching criteria
     */
    public function decisionLookup(array $criteria): array
    {
        $query = CourtDecision::with('documents');

        if (!empty($criteria['case_number'])) {
            $query->where('case_number', 'LIKE', "%{$criteria['case_number']}%");
        }

        if (!empty($criteria['court'])) {
            $query->where('court', 'LIKE', "%{$criteria['court']}%");
        }

        if (!empty($criteria['jurisdiction'])) {
            $query->where('jurisdiction', $criteria['jurisdiction']);
        }

        if (!empty($criteria['from_date'])) {
            $query->where('decision_date', '>=', $criteria['from_date']);
        }

        if (!empty($criteria['to_date'])) {
            $query->where('decision_date', '<=', $criteria['to_date']);
        }

        if (!empty($criteria['decision_type'])) {
            $query->where('decision_type', $criteria['decision_type']);
        }

        $limit = (int)($criteria['limit'] ?? 20);
        $decisions = $query->orderBy('decision_date', 'desc')->limit($limit)->get();

        return $decisions->map(fn($decision) => [
            'id' => $decision->id,
            'case_number' => $decision->case_number,
            'title' => $decision->title,
            'court' => $decision->court,
            'jurisdiction' => $decision->jurisdiction,
            'decision_date' => $decision->decision_date,
            'publication_date' => $decision->publication_date,
            'decision_type' => $decision->decision_type,
            'ecli' => $decision->ecli,
            'judge' => $decision->judge,
            'documents_count' => $decision->documents->count(),
            'summary' => $decision->documents->first()?->content ?
                Str::limit($decision->documents->first()->content, 500) : null,
        ])->toArray();
    }

    /**
     * Query the Neo4j graph database with Cypher
     *
     * @param string $cypher Cypher query
     * @param array $parameters Query parameters
     * @return array Query results
     */
    public function graphQuery(string $cypher, array $parameters = []): array
    {
        try {
            $result = $this->graph->run($cypher, $parameters);

            $rows = [];
            foreach ($result as $record) {
                $row = [];
                foreach ($record->keys() as $key) {
                    $value = $record->get($key);

                    // Convert Neo4j types to simple arrays
                    if (is_object($value)) {
                        if (method_exists($value, 'toArray')) {
                            $row[$key] = $value->toArray();
                        } elseif (method_exists($value, 'getProperties')) {
                            $row[$key] = $value->getProperties();
                        } else {
                            $row[$key] = (array)$value;
                        }
                    } else {
                        $row[$key] = $value;
                    }
                }
                $rows[] = $row;
            }

            return [
                'success' => true,
                'rows' => $rows,
                'count' => count($rows),
            ];
        } catch (\Exception $e) {
            Log::error('Graph query failed', [
                'query' => $cypher,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch content from a web URL
     *
     * @param string $url URL to fetch
     * @param array $options Fetch options:
     *   - timeout: request timeout in seconds (default: 30)
     *   - headers: additional headers
     *   - method: HTTP method (default: GET)
     * @return array Response with content, status, and headers
     */
    public function webFetch(string $url, array $options = []): array
    {
        try {
            $timeout = (int)($options['timeout'] ?? 30);
            $headers = $options['headers'] ?? [];
            $method = strtoupper($options['method'] ?? 'GET');

            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->send($method, $url);

            return [
                'success' => true,
                'status' => $response->status(),
                'content' => $response->body(),
                'headers' => $response->headers(),
                'url' => $url,
            ];
        } catch (\Exception $e) {
            Log::error('Web fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * Save an insight or note to agent vector memory
     *
     * @param string $agentName Name of the agent
     * @param string $content Content to save
     * @param array $options Save options:
     *   - namespace: memory namespace (default: 'insights')
     *   - metadata: additional metadata
     *   - source: source reference
     *   - source_id: source identifier
     * @return array Save result with memory ID
     */
    public function noteSave(string $agentName, string $content, array $options = []): array
    {
        try {
            $namespace = $options['namespace'] ?? 'insights';
            $metadata = $options['metadata'] ?? [];
            $source = $options['source'] ?? 'self_study';
            $sourceId = $options['source_id'] ?? null;

            // Generate embedding for the content
            $model = config('openai.models.embeddings');
            $embedding = $this->openai->embeddings([$content], $model);
            $vector = $embedding['data'][0]['embedding'] ?? null;

            if (!$vector) {
                return ['success' => false, 'error' => 'Failed to generate embedding'];
            }

            $contentHash = hash('sha256', $content);
            $driver = DB::connection()->getDriverName();

            // Check if already exists
            $existing = AgentVectorMemory::where('agent_name', $agentName)
                ->where('content_hash', $contentHash)
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'id' => $existing->id,
                    'status' => 'already_exists',
                ];
            }

            // Create new memory
            $memory = new AgentVectorMemory([
                'id' => (string) Str::ulid(),
                'agent_name' => $agentName,
                'namespace' => $namespace,
                'content' => $content,
                'metadata' => $metadata,
                'source' => $source,
                'source_id' => $sourceId,
                'embedding_provider' => 'openai',
                'embedding_model' => $model,
                'embedding_dimensions' => count($vector),
                'embedding_norm' => $this->norm($vector),
                'content_hash' => $contentHash,
                'token_count' => $this->estimateTokens($content),
            ]);

            if ($driver === 'pgsql') {
                $memory->embedding = DB::raw($this->toPgVectorCastLiteral($vector));
            } else {
                $memory->embedding_vector = $vector;
            }

            $memory->save();

            return [
                'success' => true,
                'id' => $memory->id,
                'status' => 'created',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to save note', [
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Convert vector to pgvector literal format
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) {
            $parts[] = rtrim(rtrim(number_format((float)$v, 8, '.', ''), '0'), '.');
        }
        return '[' . implode(',', $parts) . ']';
    }

    /**
     * Convert vector to pgvector cast literal
     */
    protected function toPgVectorCastLiteral(array $vec): string
    {
        return "'" . $this->toPgVectorLiteral($vec) . "'::vector";
    }

    /**
     * Calculate L2 norm of a vector
     */
    protected function norm(array $vec): float
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += ($v * $v);
        }
        return sqrt($sum);
    }

    /**
     * Estimate token count from text
     */
    protected function estimateTokens(string $s): int
    {
        return (int) ceil(strlen($s) / 4);
    }
}
