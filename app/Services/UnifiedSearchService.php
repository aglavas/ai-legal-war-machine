<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified search service across multiple legal corpora:
 * - Laws (from laws table)
 * - Court Decisions (from court_decision_documents table)
 * - Case Documents (from cases_documents table)
 */
class UnifiedSearchService
{
    public function __construct(
        protected OpenAIService $openai
    ) {
    }

    /**
     * Search across all corpora with filters and weights
     *
     * @param string $query The search query text
     * @param array $options Search options:
     *   - corpora: array of corpus types to search ['laws', 'decisions', 'cases'] (default: all)
     *   - weights: array of per-corpus weights ['laws' => 1.0, 'decisions' => 1.0, 'cases' => 1.0]
     *   - filters: array of filters (court, date_from, date_to, jurisdiction, decision_type, etc.)
     *   - limit: max results per corpus (default: 10)
     *   - threshold: minimum similarity score (default: 0.7)
     *   - model: embedding model to use
     * @return array Normalized search results
     */
    public function search(string $query, array $options = []): array
    {
        $corpora = $options['corpora'] ?? ['laws', 'decisions', 'cases'];
        $weights = $options['weights'] ?? [
            'laws' => 1.0,
            'decisions' => 1.0,
            'cases' => 1.0,
        ];
        $filters = $options['filters'] ?? [];
        $limit = $options['limit'] ?? 10;
        $threshold = $options['threshold'] ?? 0.7;
        $model = $options['model'] ?? config('openai.models.embeddings');

        // Generate embedding for query
        $queryEmbedding = $this->embedQuery($query, $model);

        $results = [];

        // Search each corpus
        foreach ($corpora as $corpus) {
            $weight = $weights[$corpus] ?? 1.0;

            try {
                $corpusResults = match ($corpus) {
                    'laws' => $this->searchLaws($queryEmbedding, $limit, $threshold, $filters),
                    'decisions' => $this->searchDecisions($queryEmbedding, $limit, $threshold, $filters),
                    'cases' => $this->searchCases($queryEmbedding, $limit, $threshold, $filters),
                    default => [],
                };

                // Apply corpus weight to scores
                foreach ($corpusResults as &$result) {
                    $result['score'] = $result['score'] * $weight;
                    $result['corpus_weight'] = $weight;
                }

                $results = array_merge($results, $corpusResults);
            } catch (\Exception $e) {
                Log::error("Failed to search corpus: {$corpus}", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
            }
        }

        // Sort by weighted score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // Apply overall limit
        $results = array_slice($results, 0, $limit * count($corpora));

        return [
            'query' => $query,
            'total_results' => count($results),
            'corpora' => $corpora,
            'results' => $results,
            'filters' => $filters,
        ];
    }

    /**
     * Search laws corpus
     */
    protected function searchLaws(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // Fallback for non-PostgreSQL databases
            return $this->searchLawsFallback($queryEmbedding, $limit, $threshold, $filters);
        }

        $query = DB::table('laws')
            ->select([
                'id',
                'doc_id',
                'title',
                'law_number',
                'jurisdiction',
                'country',
                'language',
                'content',
                'metadata',
                'chunk_index',
                'promulgation_date',
                'effective_date',
                DB::raw("1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ])
            ->whereRaw("1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') >= ?", [$threshold]);

        // Apply filters
        if (isset($filters['jurisdiction'])) {
            $query->where('jurisdiction', $filters['jurisdiction']);
        }
        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }
        if (isset($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (isset($filters['date_from'])) {
            $query->where('promulgation_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('promulgation_date', '<=', $filters['date_to']);
        }

        $results = $query
            ->orderByDesc('similarity')
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

            return $this->normalizeResult(
                type: 'law',
                id: $row->id,
                title: $row->title ?? "Law {$row->law_number}",
                snippet: $this->extractSnippet($row->content, 200),
                score: $row->similarity,
                metadata: [
                    'doc_id' => $row->doc_id,
                    'law_number' => $row->law_number,
                    'jurisdiction' => $row->jurisdiction,
                    'country' => $row->country,
                    'language' => $row->language,
                    'chunk_index' => $row->chunk_index,
                    'promulgation_date' => $row->promulgation_date,
                    'effective_date' => $row->effective_date,
                    'article_number' => $metadata['article_number'] ?? null,
                ]
            );
        })->toArray();
    }

    /**
     * Search court decisions corpus
     */
    protected function searchDecisions(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return $this->searchDecisionsFallback($queryEmbedding, $limit, $threshold, $filters);
        }

        $query = DB::table('court_decision_documents as cdd')
            ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
            ->select([
                'cdd.id',
                'cdd.decision_id',
                'cd.case_number',
                'cd.title',
                'cd.court',
                'cd.jurisdiction',
                'cd.decision_date',
                'cd.decision_type',
                'cd.ecli',
                'cdd.content',
                'cdd.metadata',
                'cdd.chunk_index',
                DB::raw("1 - (cdd.embedding <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ])
            ->whereRaw("1 - (cdd.embedding <=> '{$this->vectorToString($queryEmbedding)}') >= ?", [$threshold]);

        // Apply filters
        if (isset($filters['court'])) {
            $query->where('cd.court', 'LIKE', "%{$filters['court']}%");
        }
        if (isset($filters['jurisdiction'])) {
            $query->where('cd.jurisdiction', $filters['jurisdiction']);
        }
        if (isset($filters['decision_type'])) {
            $query->where('cd.decision_type', $filters['decision_type']);
        }
        if (isset($filters['date_from'])) {
            $query->where('cd.decision_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('cd.decision_date', '<=', $filters['date_to']);
        }

        $results = $query
            ->orderByDesc('similarity')
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;

            return $this->normalizeResult(
                type: 'decision',
                id: $row->id,
                title: $row->title ?? "Case {$row->case_number}",
                snippet: $this->extractSnippet($row->content, 200),
                score: $row->similarity,
                metadata: [
                    'decision_id' => $row->decision_id,
                    'case_number' => $row->case_number,
                    'court' => $row->court,
                    'jurisdiction' => $row->jurisdiction,
                    'decision_date' => $row->decision_date,
                    'decision_type' => $row->decision_type,
                    'ecli' => $row->ecli,
                    'chunk_index' => $row->chunk_index,
                ]
            );
        })->toArray();
    }

    /**
     * Search case documents corpus
     */
    protected function searchCases(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return $this->searchCasesFallback($queryEmbedding, $limit, $threshold, $filters);
        }

        $query = DB::table('cases_documents')
            ->select([
                'id',
                'case_id',
                'doc_id',
                'title',
                'category',
                'language',
                'content',
                'metadata',
                'chunk_index',
                'source',
                DB::raw("1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') as similarity"),
            ])
            ->whereRaw("1 - (embedding <=> '{$this->vectorToString($queryEmbedding)}') >= ?", [$threshold]);

        // Apply filters
        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $results = $query
            ->orderByDesc('similarity')
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            return $this->normalizeResult(
                type: 'case',
                id: $row->id,
                title: $row->title ?? "Case Document {$row->doc_id}",
                snippet: $this->extractSnippet($row->content, 200),
                score: $row->similarity,
                metadata: [
                    'case_id' => $row->case_id,
                    'doc_id' => $row->doc_id,
                    'category' => $row->category,
                    'language' => $row->language,
                    'chunk_index' => $row->chunk_index,
                    'source' => $row->source,
                ]
            );
        })->toArray();
    }

    /**
     * Fallback search for laws (non-PostgreSQL)
     */
    protected function searchLawsFallback(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        // Simple keyword-based search as fallback
        // TODO: Implement better fallback using full-text search
        return [];
    }

    /**
     * Fallback search for decisions (non-PostgreSQL)
     */
    protected function searchDecisionsFallback(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        return [];
    }

    /**
     * Fallback search for cases (non-PostgreSQL)
     */
    protected function searchCasesFallback(array $queryEmbedding, int $limit, float $threshold, array $filters): array
    {
        return [];
    }

    /**
     * Normalize search result to common format
     */
    protected function normalizeResult(
        string $type,
        string $id,
        string $title,
        string $snippet,
        float $score,
        array $metadata
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title,
            'snippet' => $snippet,
            'score' => round($score, 4),
            'metadata' => $metadata,
        ];
    }

    /**
     * Extract snippet from content
     */
    protected function extractSnippet(string $content, int $maxLength = 200): string
    {
        $content = trim($content);

        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        // Try to break at sentence boundary
        $truncated = mb_substr($content, 0, $maxLength);
        $lastPeriod = mb_strrpos($truncated, '.');
        $lastQuestion = mb_strrpos($truncated, '?');
        $lastExclaim = mb_strrpos($truncated, '!');

        $boundary = max($lastPeriod, $lastQuestion, $lastExclaim);

        if ($boundary !== false && $boundary > $maxLength * 0.6) {
            return mb_substr($content, 0, $boundary + 1);
        }

        return $truncated . '...';
    }

    /**
     * Embed query text
     */
    protected function embedQuery(string $query, string $model): array
    {
        $result = $this->openai->embeddings([$query], $model);
        return $result['data'][0]['embedding'] ?? [];
    }

    /**
     * Convert vector array to PostgreSQL vector string
     */
    protected function vectorToString(array $vector): string
    {
        $parts = array_map(function ($v) {
            return rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }, $vector);

        return '[' . implode(',', $parts) . ']';
    }

    /**
     * Hybrid search: combine vector similarity with keyword matching
     */
    public function hybridSearch(string $query, array $options = []): array
    {
        // Get vector search results
        $vectorResults = $this->search($query, $options);

        // TODO: Add keyword-based search and merge results
        // For now, just return vector results

        return $vectorResults;
    }

    /**
     * Search with citation context
     * Enhances results by including related documents via citations
     */
    public function searchWithCitations(string $query, array $options = []): array
    {
        $results = $this->search($query, $options);

        // TODO: Enhance results with citation information from GraphRagService
        // For each result, include cited laws/cases and citing documents

        return $results;
    }
}
