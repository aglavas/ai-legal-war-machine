<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * RAG (Retrieval Augmented Generation) Service for Chatbot
 * Coordinates query processing, vector search, graph search, and context building
 */
class ChatbotRAGService
{
    public function __construct(
        protected QueryProcessingService $queryProcessor,
        protected LegalEntityExtractor $entityExtractor,
        protected OpenAIService $openai,
        protected ?Neo4jService $neo4j = null
    ) {
    }

    /**
     * Retrieve relevant context for a query
     * This is the main entry point for RAG
     */
    public function retrieveContext(string $query, string $agentType = 'general', array $options = []): array
    {
        $maxResults = $options['max_results'] ?? 5;
        $minScore = $options['min_score'] ?? 0.75;

        // Step 1: Process query
        $processedQuery = $this->queryProcessor->process($query, $agentType);

        // Step 2: Determine retrieval strategy
        $strategy = $this->determineStrategy($processedQuery);

        // Step 3: Retrieve documents based on strategy
        $documents = $this->executeStrategy($strategy, $processedQuery, $maxResults, $minScore);

        // Step 4: Rerank and deduplicate
        $rankedDocs = $this->rerankDocuments($documents, $processedQuery);

        // Step 5: Build context string
        $context = $this->buildContext(array_slice($rankedDocs, 0, $maxResults));

        return [
            'context' => $context,
            'documents' => array_slice($rankedDocs, 0, $maxResults),
            'strategy' => $strategy,
            'processed_query' => $processedQuery,
            'document_count' => count($rankedDocs),
        ];
    }

    /**
     * Determine the best retrieval strategy based on query analysis
     */
    protected function determineStrategy(array $processedQuery): string
    {
        // If specific legal references found, use direct lookup
        if ($processedQuery['has_specific_refs']) {
            return 'direct_lookup';
        }

        // Based on intent
        $intent = $processedQuery['intent'];

        return match ($intent) {
            'law_lookup', 'case_lookup' => 'direct_lookup',
            'definition' => 'semantic_search',
            'procedure' => 'graph_traversal',
            'comparison' => 'hybrid_search',
            'recent_changes' => 'temporal_search',
            default => 'hybrid_search',
        };
    }

    /**
     * Execute the chosen retrieval strategy
     */
    protected function executeStrategy(
        string $strategy,
        array $processedQuery,
        int $maxResults,
        float $minScore
    ): array {
        return match ($strategy) {
            'direct_lookup' => $this->directLookup($processedQuery, $maxResults),
            'semantic_search' => $this->semanticSearch($processedQuery, $maxResults, $minScore),
            'graph_traversal' => $this->graphTraversal($processedQuery, $maxResults),
            'hybrid_search' => $this->hybridSearch($processedQuery, $maxResults, $minScore),
            'temporal_search' => $this->temporalSearch($processedQuery, $maxResults),
            default => $this->hybridSearch($processedQuery, $maxResults, $minScore),
        };
    }

    /**
     * Direct lookup for specific legal references
     */
    protected function directLookup(array $processedQuery, int $maxResults): array
    {
        $documents = [];
        $entities = $processedQuery['entities'];

        // Lookup by law references
        if (!empty($entities['laws'])) {
            foreach ($entities['laws'] as $law) {
                $lawDocs = $this->findByLawReference($law, $maxResults);
                $documents = array_merge($documents, $lawDocs);
            }
        }

        // Lookup by case numbers
        if (!empty($entities['case_numbers'])) {
            foreach ($entities['case_numbers'] as $case) {
                $caseDocs = $this->findByCaseNumber($case, $maxResults);
                $documents = array_merge($documents, $caseDocs);
            }
        }

        // Lookup by articles
        if (!empty($entities['articles'])) {
            $articleDocs = $this->findByArticle($entities['articles'], $maxResults);
            $documents = array_merge($documents, $articleDocs);
        }

        return $documents;
    }

    /**
     * Semantic vector search
     */
    protected function semanticSearch(array $processedQuery, int $maxResults, float $minScore): array
    {
        $query = $processedQuery['rewritten'] ?? $processedQuery['cleaned'];

        try {
            // Generate embedding for query
            $response = $this->openai->embeddings($query);
            $queryEmbedding = $response['data'][0]['embedding'] ?? null;

            if (!$queryEmbedding) {
                return [];
            }

            // Search in laws table using vector similarity
            $lawDocs = $this->vectorSearchLaws($queryEmbedding, $maxResults, $minScore);

            // Search in cases_documents table
            $caseDocs = $this->vectorSearchCases($queryEmbedding, $maxResults, $minScore);

            // Search in court_decision_documents table
            $decisionDocs = $this->vectorSearchCourtDecisions($queryEmbedding, $maxResults, $minScore);

            return array_merge($lawDocs, $caseDocs, $decisionDocs);
        } catch (Throwable $e) {
            Log::error('chatbot_rag.semantic_search.error', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
            return [];
        }
    }

    /**
     * Graph traversal for finding related documents
     */
    protected function graphTraversal(array $processedQuery, int $maxResults): array
    {
        if (!$this->neo4j) {
            return [];
        }

        try {
            $keywords = $processedQuery['keywords'];
            // Use Neo4j to find related documents through graph relationships
            // This would require Neo4j cypher queries - simplified here
            return [];
        } catch (Throwable $e) {
            Log::error('chatbot_rag.graph_traversal.error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Hybrid search combining multiple strategies
     */
    protected function hybridSearch(array $processedQuery, int $maxResults, float $minScore): array
    {
        // Combine semantic search with direct lookup
        $semanticDocs = $this->semanticSearch($processedQuery, $maxResults, $minScore);
        $directDocs = $this->directLookup($processedQuery, $maxResults);

        return array_merge($semanticDocs, $directDocs);
    }

    /**
     * Temporal search for recent changes
     */
    protected function temporalSearch(array $processedQuery, int $maxResults): array
    {
        $query = $processedQuery['cleaned'];

        // Search recent laws (last 2 years)
        $recentLaws = DB::table('laws')
            ->where('created_at', '>=', now()->subYears(2))
            ->where(function ($q) use ($query) {
                $q->where('content', 'like', '%' . $query . '%')
                    ->orWhere('title', 'like', '%' . $query . '%');
            })
            ->orderBy('created_at', 'desc')
            ->limit($maxResults)
            ->get();

        return $recentLaws->map(fn($law) => [
            'type' => 'law',
            'id' => $law->id,
            'title' => $law->title ?? 'Law Document',
            'content' => Str::limit($law->content, 500),
            'score' => 0.8,
            'metadata' => [
                'law_number' => $law->law_number ?? null,
                'jurisdiction' => $law->jurisdiction ?? null,
                'created_at' => $law->created_at,
            ],
        ])->toArray();
    }

    /**
     * Vector search in laws table
     */
    protected function vectorSearchLaws(array $embedding, int $limit, float $minScore): array
    {
        // PostgreSQL pgvector syntax
        $embeddingStr = '[' . implode(',', $embedding) . ']';

        $results = DB::table('laws')
            ->selectRaw('id, title, content, law_number, jurisdiction, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
            ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore])
            ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'law',
            'id' => $row->id,
            'title' => $row->title ?? 'Law Document',
            'content' => Str::limit($row->content, 500),
            'score' => $row->similarity ?? 0,
            'metadata' => [
                'law_number' => $row->law_number ?? null,
                'jurisdiction' => $row->jurisdiction ?? null,
            ],
        ])->toArray();
    }

    /**
     * Vector search in cases_documents table
     */
    protected function vectorSearchCases(array $embedding, int $limit, float $minScore): array
    {
        $embeddingStr = '[' . implode(',', $embedding) . ']';

        $results = DB::table('cases_documents')
            ->selectRaw('id, title, content, case_id, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
            ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore])
            ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'case',
            'id' => $row->id,
            'title' => $row->title ?? 'Case Document',
            'content' => Str::limit($row->content, 500),
            'score' => $row->similarity ?? 0,
            'metadata' => [
                'case_id' => $row->case_id ?? null,
            ],
        ])->toArray();
    }

    /**
     * Vector search in court_decision_documents table
     */
    protected function vectorSearchCourtDecisions(array $embedding, int $limit, float $minScore): array
    {
        $embeddingStr = '[' . implode(',', $embedding) . ']';

        $results = DB::table('court_decision_documents')
            ->selectRaw('id, title, content, court_decision_id, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
            ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore])
            ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'court_decision',
            'id' => $row->id,
            'title' => $row->title ?? 'Court Decision',
            'content' => Str::limit($row->content, 500),
            'score' => $row->similarity ?? 0,
            'metadata' => [
                'court_decision_id' => $row->court_decision_id ?? null,
            ],
        ])->toArray();
    }

    /**
     * Find documents by law reference
     */
    protected function findByLawReference(array $law, int $limit): array
    {
        $query = DB::table('laws');

        if ($law['type'] === 'law_name') {
            $query->where('title', 'like', '%' . $law['value'] . '%');
        } elseif ($law['type'] === 'nn_reference') {
            $query->where('law_number', 'like', $law['number'] . '/' . $law['year'] . '%');
        } elseif ($law['type'] === 'abbreviation') {
            $query->where('title', 'like', '%' . $law['full_name'] . '%');
        }

        $results = $query->limit($limit)->get();

        return $results->map(fn($row) => [
            'type' => 'law',
            'id' => $row->id,
            'title' => $row->title ?? 'Law Document',
            'content' => Str::limit($row->content, 500),
            'score' => 1.0, // Exact match
            'metadata' => [
                'law_number' => $row->law_number ?? null,
                'matched_reference' => $law['value'],
            ],
        ])->toArray();
    }

    /**
     * Find documents by case number
     */
    protected function findByCaseNumber(array $case, int $limit): array
    {
        $results = DB::table('cases')
            ->where('case_number', 'like', '%' . $case['full'] . '%')
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'case',
            'id' => $row->id,
            'title' => 'Case ' . $row->case_number,
            'content' => $row->description ?? 'Case document',
            'score' => 1.0, // Exact match
            'metadata' => [
                'case_number' => $row->case_number,
                'matched_reference' => $case['full'],
            ],
        ])->toArray();
    }

    /**
     * Find documents by article number
     */
    protected function findByArticle(array $articles, int $limit): array
    {
        $articleNumbers = array_column(array_filter($articles, fn($a) => $a['type'] === 'article'), 'number');

        if (empty($articleNumbers)) {
            return [];
        }

        $results = DB::table('laws')
            ->where(function ($query) use ($articleNumbers) {
                foreach ($articleNumbers as $num) {
                    $query->orWhere('content', 'like', '%članak ' . $num . '%')
                        ->orWhere('content', 'like', '%čl. ' . $num . '%');
                }
            })
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'law',
            'id' => $row->id,
            'title' => $row->title ?? 'Law Document',
            'content' => Str::limit($row->content, 500),
            'score' => 0.9,
            'metadata' => [
                'matched_articles' => $articleNumbers,
            ],
        ])->toArray();
    }

    /**
     * Rerank documents based on relevance
     */
    protected function rerankDocuments(array $documents, array $processedQuery): array
    {
        // Sort by score descending
        usort($documents, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        // Deduplicate by content hash
        $seen = [];
        $unique = [];

        foreach ($documents as $doc) {
            $hash = md5($doc['content']);
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $unique[] = $doc;
            }
        }

        return $unique;
    }

    /**
     * Build context string from retrieved documents
     */
    protected function buildContext(array $documents): string
    {
        if (empty($documents)) {
            return '';
        }

        $context = "# Relevant Legal Documents\n\n";

        foreach ($documents as $i => $doc) {
            $num = $i + 1;
            $context .= "## Document {$num}: {$doc['title']}\n";
            $context .= "**Type:** {$doc['type']}\n";
            $context .= "**Relevance:** " . round(($doc['score'] ?? 0) * 100) . "%\n\n";

            if (!empty($doc['metadata'])) {
                foreach ($doc['metadata'] as $key => $value) {
                    if ($value) {
                        $context .= "**" . ucfirst(str_replace('_', ' ', $key)) . ":** {$value}\n";
                    }
                }
                $context .= "\n";
            }

            $context .= "{$doc['content']}\n\n";
            $context .= "---\n\n";
        }

        return $context;
    }
}
