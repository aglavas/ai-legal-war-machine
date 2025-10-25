<?php

namespace App\Services;

use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RAG Orchestrator for hybrid retrieval and result fusion
 *
 * Coordinates:
 * - Query normalization and citation detection
 * - Hybrid retrieval (vector + keyword search)
 * - MMR (Maximal Marginal Relevance) for diversity
 * - RRF (Reciprocal Rank Fusion) for result merging
 * - Per-corpus caps and confidence scoring
 */
class RagOrchestrator
{
    // Default retrieval parameters
    private const DEFAULT_TOP_K = 20;
    private const DEFAULT_MMR_LAMBDA = 0.5; // Balance between relevance and diversity
    private const DEFAULT_RRF_K = 60; // Constant for RRF formula
    private const MIN_CONFIDENCE_THRESHOLD = 0.3;

    public function __construct(
        protected QueryNormalizer $queryNormalizer,
        protected HrLegalCitationsDetector $citationDetector,
        protected GraphRagService $graphRag,
        protected OpenAIService $openAI
    ) {
    }

    /**
     * Main orchestration method for RAG retrieval
     *
     * @param string $query User's query text
     * @param array $options Retrieval options
     * @return array Retrieved chunks with metadata and confidence scores
     */
    public function retrieve(string $query, array $options = []): array
    {
        // Step 1: Normalize and analyze query
        $normalizedQuery = $this->queryNormalizer->normalize($query, $options);

        // Step 2: Detect citations in query
        $citations = $this->citationDetector->detectAll($query);

        // Step 3: Generate query embedding
        $queryEmbedding = $this->openAI->createEmbedding($query);

        // Step 4: Hybrid retrieval (vector + keyword + graph)
        $vectorResults = $this->vectorSearch($queryEmbedding, $normalizedQuery, $options);
        $keywordResults = $this->keywordSearch($normalizedQuery, $options);
        $graphResults = $this->graphSearch($normalizedQuery, $citations, $options);

        // Step 5: Merge results using RRF (Reciprocal Rank Fusion)
        $mergedResults = $this->reciprocalRankFusion([
            'vector' => $vectorResults,
            'keyword' => $keywordResults,
            'graph' => $graphResults,
        ], $options['rrf_k'] ?? self::DEFAULT_RRF_K);

        // Step 6: Apply MMR for diversity
        $diverseResults = $this->maximalMarginalRelevance(
            $mergedResults,
            $queryEmbedding,
            $options['mmr_lambda'] ?? self::DEFAULT_MMR_LAMBDA,
            $options['top_k'] ?? self::DEFAULT_TOP_K
        );

        // Step 7: Apply per-corpus caps
        $cappedResults = $this->applyCorpusCaps($diverseResults, $options['corpus_caps'] ?? []);

        // Step 8: Calculate confidence scores
        $scoredResults = $this->calculateConfidence($cappedResults, $normalizedQuery, $citations);

        // Step 9: Enrich with metadata
        $enrichedResults = $this->enrichMetadata($scoredResults, $normalizedQuery);

        return [
            'query_analysis' => $normalizedQuery,
            'citations_detected' => $citations,
            'chunks' => $enrichedResults,
            'retrieval_stats' => [
                'vector_results' => count($vectorResults),
                'keyword_results' => count($keywordResults),
                'graph_results' => count($graphResults),
                'merged_results' => count($mergedResults),
                'final_results' => count($enrichedResults),
            ],
        ];
    }

    /**
     * Vector similarity search across all corpora
     */
    protected function vectorSearch(array $queryEmbedding, array $normalizedQuery, array $options): array
    {
        $results = [];
        $limit = $options['vector_limit'] ?? 50;
        $similarityThreshold = $options['similarity_threshold'] ?? 0.7;

        // Search laws corpus
        if (!isset($options['exclude_corpora']) || !in_array('laws', $options['exclude_corpora'])) {
            $lawResults = $this->vectorSearchCorpus(
                'laws',
                $queryEmbedding,
                $limit,
                $similarityThreshold,
                $normalizedQuery
            );
            $results = array_merge($results, $lawResults);
        }

        // Search cases corpus
        if (!isset($options['exclude_corpora']) || !in_array('cases', $options['exclude_corpora'])) {
            $caseResults = $this->vectorSearchCorpus(
                'cases_documents',
                $queryEmbedding,
                $limit,
                $similarityThreshold,
                $normalizedQuery
            );
            $results = array_merge($results, $caseResults);
        }

        // Search court decisions corpus
        if (!isset($options['exclude_corpora']) || !in_array('decisions', $options['exclude_corpora'])) {
            $decisionResults = $this->vectorSearchCorpus(
                'court_decision_documents',
                $queryEmbedding,
                $limit,
                $similarityThreshold,
                $normalizedQuery
            );
            $results = array_merge($results, $decisionResults);
        }

        return $results;
    }

    /**
     * Vector search within a specific corpus
     */
    protected function vectorSearchCorpus(
        string $table,
        array $queryEmbedding,
        int $limit,
        float $threshold,
        array $normalizedQuery
    ): array {
        $embeddingJson = json_encode($queryEmbedding);

        // Check if using pgvector or JSON embeddings
        $usingPgVector = DB::connection()->getDriverName() === 'pgsql'
            && DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

        if ($usingPgVector) {
            // Use pgvector for efficient similarity search
            $sql = "SELECT
                id, doc_id, title, content, metadata, chunk_index,
                1 - (embedding <=> ?::vector) as similarity,
                ? as corpus
            FROM {$table}
            WHERE 1 - (embedding <=> ?::vector) >= ?
            ORDER BY embedding <=> ?::vector
            LIMIT ?";

            $results = DB::select($sql, [
                $embeddingJson,
                $table,
                $embeddingJson,
                $threshold,
                $embeddingJson,
                $limit
            ]);
        } else {
            // Fallback to computing cosine similarity in PHP
            $documents = DB::table($table)
                ->whereNotNull('embedding_vector')
                ->limit($limit * 3) // Get more to compensate for filtering
                ->get();

            $results = [];
            foreach ($documents as $doc) {
                $docEmbedding = json_decode($doc->embedding_vector ?? '[]', true);
                $similarity = $this->cosineSimilarity($queryEmbedding, $docEmbedding);

                if ($similarity >= $threshold) {
                    $results[] = (object)[
                        'id' => $doc->id,
                        'doc_id' => $doc->doc_id,
                        'title' => $doc->title,
                        'content' => $doc->content,
                        'metadata' => $doc->metadata,
                        'chunk_index' => $doc->chunk_index,
                        'similarity' => $similarity,
                        'corpus' => $table,
                    ];
                }
            }

            // Sort by similarity and limit
            usort($results, fn($a, $b) => $b->similarity <=> $a->similarity);
            $results = array_slice($results, 0, $limit);
        }

        return array_map(fn($r) => [
            'id' => $r->id,
            'doc_id' => $r->doc_id,
            'title' => $r->title,
            'content' => $r->content,
            'metadata' => is_string($r->metadata) ? json_decode($r->metadata, true) : $r->metadata,
            'chunk_index' => $r->chunk_index,
            'score' => $r->similarity,
            'corpus' => $r->corpus,
            'retrieval_method' => 'vector',
        ], $results);
    }

    /**
     * Keyword-based search using PostgreSQL full-text search or simple LIKE
     */
    protected function keywordSearch(array $normalizedQuery, array $options): array
    {
        $results = [];
        $keywords = $normalizedQuery['ključne_riječi'] ?? [];
        $limit = $options['keyword_limit'] ?? 30;

        if (empty($keywords)) {
            return [];
        }

        // Build search query
        $searchTerms = array_map(fn($kw) => "%{$kw}%", $keywords);

        // Search laws
        if (!isset($options['exclude_corpora']) || !in_array('laws', $options['exclude_corpora'])) {
            $lawResults = DB::table('laws')
                ->where(function($query) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $query->orWhere('content', 'ILIKE', $term)
                              ->orWhere('title', 'ILIKE', $term);
                    }
                })
                ->limit($limit)
                ->get();

            foreach ($lawResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'doc_id' => $result->doc_id,
                    'title' => $result->title,
                    'content' => $result->content,
                    'metadata' => json_decode($result->metadata ?? '{}', true),
                    'chunk_index' => $result->chunk_index,
                    'score' => $this->calculateKeywordScore($result->content, $keywords),
                    'corpus' => 'laws',
                    'retrieval_method' => 'keyword',
                ];
            }
        }

        // Search cases
        if (!isset($options['exclude_corpora']) || !in_array('cases', $options['exclude_corpora'])) {
            $caseResults = DB::table('cases_documents')
                ->where(function($query) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $query->orWhere('content', 'ILIKE', $term)
                              ->orWhere('title', 'ILIKE', $term);
                    }
                })
                ->limit($limit)
                ->get();

            foreach ($caseResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'doc_id' => $result->doc_id,
                    'title' => $result->title,
                    'content' => $result->content,
                    'metadata' => json_decode($result->metadata ?? '{}', true),
                    'chunk_index' => $result->chunk_index,
                    'score' => $this->calculateKeywordScore($result->content, $keywords),
                    'corpus' => 'cases_documents',
                    'retrieval_method' => 'keyword',
                ];
            }
        }

        return $results;
    }

    /**
     * Graph-based retrieval using citations and relationships
     */
    protected function graphSearch(array $normalizedQuery, array $citations, array $options): array
    {
        $results = [];
        $limit = $options['graph_limit'] ?? 20;

        // Extract law numbers from citations
        $lawNumbers = [];
        foreach ($citations['narodne_novine'] ?? [] as $nn) {
            if (isset($nn['issues'])) {
                $lawNumbers = array_merge($lawNumbers, $nn['issues']);
            }
        }

        // Extract case numbers
        $caseNumbers = [];
        foreach ($citations['case_numbers'] ?? [] as $case) {
            if (isset($case['canonical'])) {
                $caseNumbers[] = $case['canonical'];
            }
        }

        // Find laws by citation
        if (!empty($lawNumbers)) {
            foreach ($lawNumbers as $lawNumber) {
                $laws = DB::table('laws')
                    ->where('law_number', $lawNumber)
                    ->limit(10)
                    ->get();

                foreach ($laws as $law) {
                    $results[] = [
                        'id' => $law->id,
                        'doc_id' => $law->doc_id,
                        'title' => $law->title,
                        'content' => $law->content,
                        'metadata' => json_decode($law->metadata ?? '{}', true),
                        'chunk_index' => $law->chunk_index,
                        'score' => 0.95, // High score for direct citation match
                        'corpus' => 'laws',
                        'retrieval_method' => 'graph_citation',
                    ];
                }
            }
        }

        // Find cases by citation
        if (!empty($caseNumbers)) {
            foreach ($caseNumbers as $caseNumber) {
                $cases = DB::table('cases_documents')
                    ->where('doc_id', 'LIKE', "%{$caseNumber}%")
                    ->limit(10)
                    ->get();

                foreach ($cases as $case) {
                    $results[] = [
                        'id' => $case->id,
                        'doc_id' => $case->doc_id,
                        'title' => $case->title,
                        'content' => $case->content,
                        'metadata' => json_decode($case->metadata ?? '{}', true),
                        'chunk_index' => $case->chunk_index,
                        'score' => 0.95, // High score for direct citation match
                        'corpus' => 'cases_documents',
                        'retrieval_method' => 'graph_citation',
                    ];
                }
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Reciprocal Rank Fusion (RRF) - merges multiple ranked lists
     * Formula: RRF(d) = Σ 1/(k + rank(d))
     */
    protected function reciprocalRankFusion(array $rankedLists, int $k = 60): array
    {
        $documentScores = [];

        foreach ($rankedLists as $listName => $documents) {
            foreach ($documents as $rank => $doc) {
                $docKey = $doc['corpus'] . ':' . $doc['id'];

                if (!isset($documentScores[$docKey])) {
                    $documentScores[$docKey] = [
                        'doc' => $doc,
                        'rrf_score' => 0,
                        'sources' => [],
                    ];
                }

                // RRF formula: 1 / (k + rank)
                $rrfContribution = 1.0 / ($k + $rank + 1);
                $documentScores[$docKey]['rrf_score'] += $rrfContribution;
                $documentScores[$docKey]['sources'][] = [
                    'method' => $listName,
                    'rank' => $rank,
                    'original_score' => $doc['score'],
                    'rrf_contribution' => $rrfContribution,
                ];
            }
        }

        // Sort by RRF score
        uasort($documentScores, fn($a, $b) => $b['rrf_score'] <=> $a['rrf_score']);

        // Extract documents and add RRF score
        $mergedDocs = [];
        foreach ($documentScores as $docData) {
            $doc = $docData['doc'];
            $doc['rrf_score'] = $docData['rrf_score'];
            $doc['rrf_sources'] = $docData['sources'];
            $mergedDocs[] = $doc;
        }

        return $mergedDocs;
    }

    /**
     * Maximal Marginal Relevance (MMR) - balances relevance and diversity
     * MMR = λ * Sim(D, Q) - (1-λ) * max Sim(D, Di)
     */
    protected function maximalMarginalRelevance(
        array $candidates,
        array $queryEmbedding,
        float $lambda,
        int $topK
    ): array {
        if (empty($candidates)) {
            return [];
        }

        $selected = [];
        $remaining = $candidates;

        // Precompute embeddings for all candidates
        $candidateEmbeddings = [];
        foreach ($remaining as $idx => $doc) {
            // Try to get embedding from database or compute on the fly
            $embedding = $this->getDocumentEmbedding($doc);
            if ($embedding) {
                $candidateEmbeddings[$idx] = $embedding;
            }
        }

        while (count($selected) < $topK && !empty($remaining)) {
            $bestScore = -INF;
            $bestIdx = null;

            foreach ($remaining as $idx => $doc) {
                if (!isset($candidateEmbeddings[$idx])) {
                    continue;
                }

                $docEmbedding = $candidateEmbeddings[$idx];

                // Relevance to query
                $relevance = $this->cosineSimilarity($docEmbedding, $queryEmbedding);

                // Diversity (similarity to already selected documents)
                $maxSimilarity = 0;
                foreach ($selected as $selectedDoc) {
                    $selectedIdx = $selectedDoc['_original_idx'];
                    if (isset($candidateEmbeddings[$selectedIdx])) {
                        $similarity = $this->cosineSimilarity(
                            $docEmbedding,
                            $candidateEmbeddings[$selectedIdx]
                        );
                        $maxSimilarity = max($maxSimilarity, $similarity);
                    }
                }

                // MMR formula
                $mmrScore = $lambda * $relevance - (1 - $lambda) * $maxSimilarity;

                if ($mmrScore > $bestScore) {
                    $bestScore = $mmrScore;
                    $bestIdx = $idx;
                }
            }

            if ($bestIdx !== null) {
                $selectedDoc = $remaining[$bestIdx];
                $selectedDoc['mmr_score'] = $bestScore;
                $selectedDoc['_original_idx'] = $bestIdx;
                $selected[] = $selectedDoc;
                unset($remaining[$bestIdx]);
            } else {
                break; // No valid candidates remaining
            }
        }

        return $selected;
    }

    /**
     * Apply per-corpus result caps
     */
    protected function applyCorpusCaps(array $results, array $corpusCaps): array
    {
        if (empty($corpusCaps)) {
            return $results;
        }

        $corpusCounts = [];
        $cappedResults = [];

        foreach ($results as $result) {
            $corpus = $result['corpus'];
            $cap = $corpusCaps[$corpus] ?? PHP_INT_MAX;

            $currentCount = $corpusCounts[$corpus] ?? 0;

            if ($currentCount < $cap) {
                $cappedResults[] = $result;
                $corpusCounts[$corpus] = $currentCount + 1;
            }
        }

        return $cappedResults;
    }

    /**
     * Calculate confidence scores for retrieved chunks
     */
    protected function calculateConfidence(array $results, array $normalizedQuery, array $citations): array
    {
        foreach ($results as &$result) {
            $confidence = 0;

            // Base confidence from retrieval score
            if (isset($result['mmr_score'])) {
                $confidence += $result['mmr_score'] * 0.4;
            } elseif (isset($result['rrf_score'])) {
                $confidence += min($result['rrf_score'] / 10, 1.0) * 0.4;
            } elseif (isset($result['score'])) {
                $confidence += $result['score'] * 0.4;
            }

            // Boost for citation matches
            if ($result['retrieval_method'] === 'graph_citation') {
                $confidence += 0.3;
            }

            // Boost for keyword matches
            $keywordScore = $this->calculateKeywordScore(
                $result['content'],
                $normalizedQuery['ključne_riječi'] ?? []
            );
            $confidence += $keywordScore * 0.2;

            // Boost for multiple retrieval methods
            if (isset($result['rrf_sources']) && count($result['rrf_sources']) > 1) {
                $confidence += 0.1;
            }

            // Normalize to [0, 1]
            $result['confidence'] = min(max($confidence, 0), 1.0);
        }

        // Filter out low-confidence results
        $results = array_filter($results, fn($r) =>
            $r['confidence'] >= self::MIN_CONFIDENCE_THRESHOLD
        );

        return array_values($results);
    }

    /**
     * Enrich results with additional metadata
     */
    protected function enrichMetadata(array $results, array $normalizedQuery): array
    {
        foreach ($results as &$result) {
            $result['_metadata'] = [
                'query_jurisdiction' => $normalizedQuery['jurisdikcija'] ?? null,
                'query_case_id' => $normalizedQuery['case_id'] ?? null,
                'chunk_length' => mb_strlen($result['content']),
                'corpus_type' => $this->getCorpusType($result['corpus']),
            ];
        }

        return $results;
    }

    /**
     * Calculate keyword score for a document
     */
    protected function calculateKeywordScore(string $content, array $keywords): float
    {
        if (empty($keywords)) {
            return 0;
        }

        $contentLower = mb_strtolower($content);
        $matches = 0;

        foreach ($keywords as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            if (mb_strpos($contentLower, $keywordLower) !== false) {
                $matches++;
            }
        }

        return $matches / count($keywords);
    }

    /**
     * Get document embedding (from cache or compute)
     */
    protected function getDocumentEmbedding(array $doc): ?array
    {
        // Try to fetch from database if we have the ID
        if (isset($doc['id']) && isset($doc['corpus'])) {
            $result = DB::table($doc['corpus'])
                ->where('id', $doc['id'])
                ->first(['embedding_vector']);

            if ($result && $result->embedding_vector) {
                $embedding = json_decode($result->embedding_vector, true);
                if (is_array($embedding)) {
                    return $embedding;
                }
            }
        }

        return null;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2) || empty($vec1)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Get corpus type for metadata
     */
    protected function getCorpusType(string $corpus): string
    {
        return match($corpus) {
            'laws' => 'legislation',
            'cases_documents' => 'case_law',
            'court_decision_documents' => 'court_decisions',
            default => 'unknown',
        };
    }
}
