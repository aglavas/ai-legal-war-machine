<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enhanced RAG (Retrieval Augmented Generation) Service for Chatbot
 * Coordinates query processing, vector search, and context building with full document retrieval
 */
class ChatbotRAGService
{
    // Token budget limits
    const MAX_CONTEXT_TOKENS = 80000; // Conservative limit (models support 100k-200k)
    const AVERAGE_TOKENS_PER_CHAR = 0.25; // 1 token ≈ 4 characters

    // Document limits per source
    const MAX_LAWS_PER_QUERY = 10;
    const MAX_CASES_PER_QUERY = 7;
    const MAX_COURT_DECISIONS_PER_QUERY = 7;

    public function __construct(
        protected QueryProcessingService $queryProcessor,
        protected HrLegalCitationsDetector $citationDetector,
        protected OpenAIService $openai,
        protected ?Neo4jService $neo4j = null
    ) {
    }

    /**
     * Retrieve relevant context for a query with full document content
     */
    public function retrieveContext(string $query, string $agentType = 'general', array $options = []): array
    {
        // Enhanced defaults for comprehensive retrieval
        $maxTokens = $options['max_tokens'] ?? self::MAX_CONTEXT_TOKENS;
        $minScore = $options['min_score'] ?? 0.70; // Lowered for better recall
        $includeFullContent = $options['include_full_content'] ?? true;

        // Step 1: Process query
        $processedQuery = $this->queryProcessor->process($query, $agentType);

        // Step 2: Determine retrieval strategy
        $strategy = $this->determineStrategy($processedQuery);

        // Step 3: Retrieve documents from ALL sources simultaneously
        $allDocuments = $this->retrieveFromAllSources(
            $processedQuery,
            $strategy,
            $minScore,
            $agentType
        );

        // Step 4: Prioritize, rerank, and manage token budget
        $finalDocuments = $this->prioritizeAndBudget($allDocuments, $processedQuery, $maxTokens);

        // Step 5: Build comprehensive context with full content
        $context = $this->buildEnhancedContext($finalDocuments, $includeFullContent);

        $totalTokens = $this->calculateTotalTokens($finalDocuments);

        return [
            'context' => $context,
            'documents' => $finalDocuments,
            'strategy' => $strategy,
            'processed_query' => $processedQuery,
            'document_count' => count($finalDocuments),
            'total_tokens' => $totalTokens,
            'sources_breakdown' => $this->getSourcesBreakdown($finalDocuments),
            'budget_utilization' => round(($totalTokens / $maxTokens) * 100, 1) . '%',
        ];
    }

    /**
     * Retrieve documents from all sources (laws, cases, court decisions) simultaneously
     */
    protected function retrieveFromAllSources(
        array $processedQuery,
        string $strategy,
        float $minScore,
        string $agentType
    ): array {
        $allDocs = [];

        // Priority based on agent type
        $priorities = match($agentType) {
            'law' => ['laws' => 1.3, 'court_decisions' => 1.0, 'cases' => 0.8],
            'court_decision' => ['court_decisions' => 1.3, 'laws' => 1.0, 'cases' => 0.9],
            'case_analysis' => ['cases' => 1.3, 'laws' => 1.0, 'court_decisions' => 1.0],
            default => ['laws' => 1.0, 'cases' => 1.0, 'court_decisions' => 1.0],
        };

        // Retrieve from laws
        $lawDocs = $this->retrieveLaws($processedQuery, $strategy, $minScore);
        foreach ($lawDocs as $doc) {
            $doc['priority_boost'] = $priorities['laws'];
            $allDocs[] = $doc;
        }

        // Retrieve from cases
        $caseDocs = $this->retrieveCases($processedQuery, $strategy, $minScore);
        foreach ($caseDocs as $doc) {
            $doc['priority_boost'] = $priorities['cases'];
            $allDocs[] = $doc;
        }

        // Retrieve from court decisions
        $courtDocs = $this->retrieveCourtDecisions($processedQuery, $strategy, $minScore);
        foreach ($courtDocs as $doc) {
            $doc['priority_boost'] = $priorities['court_decisions'];
            $allDocs[] = $doc;
        }

        // Deduplicate across ALL sources (not just within each source)
        $allDocs = $this->deduplicateDocuments($allDocs);

        Log::debug('chatbot_rag.sources_retrieved', [
            'total_docs' => count($allDocs),
            'laws' => count($lawDocs),
            'cases' => count($caseDocs),
            'court_decisions' => count($courtDocs),
            'agent_type' => $agentType,
        ]);

        return $allDocs;
    }

    /**
     * Retrieve laws with full content (articles are already chunked)
     */
    protected function retrieveLaws(array $processedQuery, string $strategy, float $minScore): array
    {
        $documents = [];
        $entities = $processedQuery['entities'];

        // Direct lookup if specific law references exist
        if (!empty($entities['laws'])) {
            foreach ($entities['laws'] as $law) {
                $lawDocs = $this->findLawsByReference($law, self::MAX_LAWS_PER_QUERY);
                $documents = array_merge($documents, $lawDocs);
            }
        }

        // Article-specific lookup
        if (!empty($entities['articles'])) {
            $articleDocs = $this->findLawsByArticle($entities['articles'], self::MAX_LAWS_PER_QUERY);
            $documents = array_merge($documents, $articleDocs);
        }

        // Semantic search if no specific references or additional context needed
        if (empty($documents) || $strategy === 'hybrid_search') {
            try {
                $semanticDocs = $this->vectorSearchLaws(
                    $processedQuery['rewritten'] ?? $processedQuery['cleaned'],
                    self::MAX_LAWS_PER_QUERY,
                    $minScore
                );
                $documents = array_merge($documents, $semanticDocs);
            } catch (Throwable $e) {
                Log::warning('chatbot_rag.vector_search_laws.error', ['error' => $e->getMessage()]);
            }
        }

        return $this->deduplicateDocuments($documents);
    }

    /**
     * Retrieve cases with full content
     */
    protected function retrieveCases(array $processedQuery, string $strategy, float $minScore): array
    {
        $documents = [];
        $entities = $processedQuery['entities'];

        // Direct lookup by case number
        if (!empty($entities['case_numbers'])) {
            foreach ($entities['case_numbers'] as $case) {
                $caseDocs = $this->findCasesByNumber($case, self::MAX_CASES_PER_QUERY);
                $documents = array_merge($documents, $caseDocs);
            }
        }

        // Semantic search for case documents
        if (empty($documents) || $strategy !== 'direct_lookup') {
            try {
                $semanticDocs = $this->vectorSearchCases(
                    $processedQuery['rewritten'] ?? $processedQuery['cleaned'],
                    self::MAX_CASES_PER_QUERY,
                    $minScore
                );
                $documents = array_merge($documents, $semanticDocs);
            } catch (Throwable $e) {
                Log::warning('chatbot_rag.vector_search_cases.error', ['error' => $e->getMessage()]);
            }
        }

        return $this->deduplicateDocuments($documents);
    }

    /**
     * Retrieve court decisions with full content
     */
    protected function retrieveCourtDecisions(array $processedQuery, string $strategy, float $minScore): array
    {
        $documents = [];
        $entities = $processedQuery['entities'];

        // Filter by court type if specified
        $courtTypes = !empty($entities['court_types']) ? array_column($entities['court_types'], 'type') : null;

        // Semantic search for court decisions
        try {
            $semanticDocs = $this->vectorSearchCourtDecisions(
                $processedQuery['rewritten'] ?? $processedQuery['cleaned'],
                self::MAX_COURT_DECISIONS_PER_QUERY,
                $minScore,
                $courtTypes
            );
            $documents = array_merge($documents, $semanticDocs);
        } catch (Throwable $e) {
            Log::warning('chatbot_rag.vector_search_court_decisions.error', ['error' => $e->getMessage()]);
        }

        return $this->deduplicateDocuments($documents);
    }

    /**
     * Vector search in laws table with FULL content
     */
    protected function vectorSearchLaws(string $query, int $limit, float $minScore): array
    {
        try {
            // Generate embedding for query
            $response = $this->openai->embeddings($query);
            $queryEmbedding = $response['data'][0]['embedding'] ?? null;

            if (!$queryEmbedding) {
                Log::warning('chatbot_rag.vector_search_laws.no_embedding', [
                    'query' => Str::limit($query, 100),
                ]);
                return [];
            }

            $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';

            // Retrieve with FULL content (no truncation)
            $results = DB::table('laws')
                ->selectRaw('id, doc_id, title, content, law_number, jurisdiction, chunk_index, token_count, metadata, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
                ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore])
                ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
                ->limit($limit)
                ->get();

            Log::debug('chatbot_rag.vector_search_laws.results', [
                'query' => Str::limit($query, 100),
                'found' => count($results),
                'limit' => $limit,
                'min_score' => $minScore,
            ]);

            return $results->map(fn($row) => [
                'type' => 'law',
                'id' => $row->id,
                'doc_id' => $row->doc_id,
                'title' => $row->title ?? 'Law Document',
                'content' => $row->content, // FULL content, not truncated
                'score' => $row->similarity ?? 0,
                'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
                'metadata' => [
                    'law_number' => $row->law_number ?? null,
                    'jurisdiction' => $row->jurisdiction ?? null,
                    'chunk_index' => $row->chunk_index ?? null,
                    'source_metadata' => json_decode($row->metadata ?? '{}', true),
                ],
            ])->toArray();
        } catch (Throwable $e) {
            Log::error('chatbot_rag.vector_search_laws.error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => Str::limit($query, 100),
            ]);
            return [];
        }
    }

    /**
     * Vector search in cases_documents table with FULL content
     */
    protected function vectorSearchCases(string $query, int $limit, float $minScore): array
    {
        try {
            $response = $this->openai->embeddings($query);
            $queryEmbedding = $response['data'][0]['embedding'] ?? null;

            if (!$queryEmbedding) {
                return [];
            }

            $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';

            $results = DB::table('cases_documents')
                ->selectRaw('id, case_id, doc_id, title, content, category, token_count, metadata, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
                ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore])
                ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
                ->limit($limit)
                ->get();

            return $results->map(fn($row) => [
                'type' => 'case',
                'id' => $row->id,
                'doc_id' => $row->doc_id,
                'case_id' => $row->case_id,
                'title' => $row->title ?? 'Case Document',
                'content' => $row->content, // FULL content
                'score' => $row->similarity ?? 0,
                'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
                'metadata' => [
                    'category' => $row->category ?? null,
                    'source_metadata' => json_decode($row->metadata ?? '{}', true),
                ],
            ])->toArray();
        } catch (Throwable $e) {
            Log::error('chatbot_rag.vector_search_cases.error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Vector search in court_decision_documents table with FULL content
     */
    protected function vectorSearchCourtDecisions(string $query, int $limit, float $minScore, ?array $courtTypes = null): array
    {
        try {
            $response = $this->openai->embeddings($query);
            $queryEmbedding = $response['data'][0]['embedding'] ?? null;

            if (!$queryEmbedding) {
                return [];
            }

            $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';

            $queryBuilder = DB::table('court_decision_documents')
                ->selectRaw('id, court_decision_id, doc_id, title, content, token_count, metadata, (1 - (embedding_vector <=> ?::vector)) as similarity', [$embeddingStr])
                ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$embeddingStr, $minScore]);

            // Filter by court types if specified
            if ($courtTypes) {
                $queryBuilder->whereIn('court_type', $courtTypes);
            }

            $results = $queryBuilder
                ->orderByRaw('embedding_vector <=> ?::vector', [$embeddingStr])
                ->limit($limit)
                ->get();

            return $results->map(fn($row) => [
                'type' => 'court_decision',
                'id' => $row->id,
                'doc_id' => $row->doc_id,
                'court_decision_id' => $row->court_decision_id,
                'title' => $row->title ?? 'Court Decision',
                'content' => $row->content, // FULL content
                'score' => $row->similarity ?? 0,
                'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
                'metadata' => [
                    'source_metadata' => json_decode($row->metadata ?? '{}', true),
                ],
            ])->toArray();
        } catch (Throwable $e) {
            Log::error('chatbot_rag.vector_search_court_decisions.error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Find laws by specific reference (NN, law name, abbreviation)
     */
    protected function findLawsByReference(array $law, int $limit): array
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
            'doc_id' => $row->doc_id ?? null,
            'title' => $row->title ?? 'Law Document',
            'content' => $row->content, // FULL content
            'score' => 1.0, // Exact match gets perfect score
            'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
            'metadata' => [
                'law_number' => $row->law_number ?? null,
                'matched_reference' => $law['value'],
                'exact_match' => true,
            ],
        ])->toArray();
    }

    /**
     * Find laws by article number
     */
    protected function findLawsByArticle(array $articles, int $limit): array
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
            'doc_id' => $row->doc_id ?? null,
            'title' => $row->title ?? 'Law Document',
            'content' => $row->content, // FULL content
            'score' => 0.95, // Near-perfect for article matches
            'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
            'metadata' => [
                'matched_articles' => $articleNumbers,
                'law_number' => $row->law_number ?? null,
            ],
        ])->toArray();
    }

    /**
     * Find cases by case number
     */
    protected function findCasesByNumber(array $caseRef, int $limit): array
    {
        // First try to find in cases_documents through cases table
        $results = DB::table('cases_documents as cd')
            ->join('cases as c', 'cd.case_id', '=', 'c.id')
            ->where('c.case_number', 'like', '%' . $caseRef['full'] . '%')
            ->select('cd.*')
            ->limit($limit)
            ->get();

        return $results->map(fn($row) => [
            'type' => 'case',
            'id' => $row->id,
            'doc_id' => $row->doc_id ?? null,
            'case_id' => $row->case_id,
            'title' => $row->title ?? 'Case ' . $caseRef['full'],
            'content' => $row->content, // FULL content
            'score' => 1.0, // Exact match
            'token_count' => $row->token_count ?? $this->estimateTokens($row->content),
            'metadata' => [
                'case_number' => $caseRef['full'],
                'exact_match' => true,
            ],
        ])->toArray();
    }

    /**
     * Deduplicate documents by content hash
     */
    protected function deduplicateDocuments(array $documents): array
    {
        $seen = [];
        $unique = [];

        foreach ($documents as $doc) {
            $hash = md5($doc['content'] ?? '');
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $unique[] = $doc;
            }
        }

        return $unique;
    }

    /**
     * Prioritize documents and manage token budget
     * Ensures we stay within token limits while prioritizing most relevant docs
     */
    protected function prioritizeAndBudget(array $documents, array $processedQuery, int $maxTokens): array
    {
        // Calculate adjusted scores with priority boosts
        foreach ($documents as &$doc) {
            $priorityBoost = $doc['priority_boost'] ?? 1.0;
            $doc['adjusted_score'] = ($doc['score'] ?? 0) * $priorityBoost;
        }
        unset($doc); // Break reference

        // Sort by adjusted score (highest first)
        usort($documents, fn($a, $b) => ($b['adjusted_score'] ?? 0) <=> ($a['adjusted_score'] ?? 0));

        // Apply token budget with greedy selection
        $selectedDocs = [];
        $totalTokens = 0;

        foreach ($documents as $doc) {
            $docTokens = $doc['token_count'] ?? $this->estimateTokens($doc['content'] ?? '');

            if ($totalTokens + $docTokens <= $maxTokens) {
                // Document fits within budget
                $selectedDocs[] = $doc;
                $totalTokens += $docTokens;
            } else {
                // Budget exceeded - make exception for first 3 critical documents
                if (count($selectedDocs) < 3) {
                    $selectedDocs[] = $doc;
                    $totalTokens += $docTokens;
                    Log::debug('chatbot_rag.budget_exceeded_but_critical', [
                        'doc_title' => Str::limit($doc['title'] ?? 'Unknown', 50),
                        'doc_tokens' => $docTokens,
                        'total_tokens' => $totalTokens,
                        'budget' => $maxTokens,
                    ]);
                }
                break; // Stop processing once we've exceeded budget
            }
        }

        Log::debug('chatbot_rag.budget_applied', [
            'input_docs' => count($documents),
            'selected_docs' => count($selectedDocs),
            'total_tokens' => $totalTokens,
            'budget' => $maxTokens,
            'utilization' => round(($totalTokens / $maxTokens) * 100, 1) . '%',
        ]);

        return $selectedDocs;
    }

    /**
     * Build enhanced context with full document content
     */
    protected function buildEnhancedContext(array $documents, bool $includeFullContent = true): string
    {
        if (empty($documents)) {
            return '';
        }

        $context = "# Retrieved Legal Documents\n\n";
        $context .= "_The following documents have been retrieved and are relevant to your query. ";
        $context .= "Please cite these documents when answering._\n\n";
        $context .= "---\n\n";

        // Group by type
        $byType = [
            'law' => [],
            'case' => [],
            'court_decision' => [],
        ];

        foreach ($documents as $doc) {
            $type = $doc['type'] ?? 'unknown';
            if (isset($byType[$type])) {
                $byType[$type][] = $doc;
            }
        }

        // Output each type
        foreach ($byType as $type => $docs) {
            if (empty($docs)) {
                continue;
            }

            $typeLabel = match($type) {
                'law' => 'Laws and Regulations',
                'case' => 'Case Documents',
                'court_decision' => 'Court Decisions',
                default => 'Documents',
            };

            $context .= "## {$typeLabel}\n\n";

            foreach ($docs as $i => $doc) {
                $num = $i + 1;
                $title = $doc['title'] ?? 'Document';
                $relevance = round(($doc['score'] ?? 0) * 100);

                $context .= "### Document {$num}: {$title}\n\n";
                $context .= "- **Type:** " . ucfirst($doc['type']) . "\n";
                $context .= "- **Relevance:** {$relevance}%\n";

                if (!empty($doc['metadata'])) {
                    if (isset($doc['metadata']['law_number'])) {
                        $context .= "- **Law Number:** " . $doc['metadata']['law_number'] . "\n";
                    }
                    if (isset($doc['metadata']['jurisdiction'])) {
                        $context .= "- **Jurisdiction:** " . $doc['metadata']['jurisdiction'] . "\n";
                    }
                    if (isset($doc['metadata']['case_number'])) {
                        $context .= "- **Case Number:** " . $doc['metadata']['case_number'] . "\n";
                    }
                    if (isset($doc['metadata']['exact_match']) && $doc['metadata']['exact_match']) {
                        $context .= "- **Match Type:** Exact reference match\n";
                    }
                }

                $context .= "\n**Content:**\n\n";

                // Include FULL content if flag is set (default)
                if ($includeFullContent) {
                    $context .= $doc['content'] . "\n\n";
                } else {
                    // Fallback to truncated (shouldn't happen normally)
                    $context .= Str::limit($doc['content'], 500) . "\n\n";
                }

                $context .= "---\n\n";
            }
        }

        return $context;
    }

    /**
     * Determine retrieval strategy
     */
    protected function determineStrategy(array $processedQuery): string
    {
        if ($processedQuery['has_specific_refs']) {
            return 'hybrid_search'; // Changed from direct_lookup to get both exact + semantic
        }

        $intent = $processedQuery['intent'];

        return match ($intent) {
            'law_lookup', 'case_lookup' => 'hybrid_search',
            'definition' => 'semantic_search',
            'procedure' => 'semantic_search',
            'comparison' => 'hybrid_search',
            'recent_changes' => 'temporal_search',
            default => 'hybrid_search',
        };
    }

    /**
     * Get breakdown of documents by source type
     */
    protected function getSourcesBreakdown(array $documents): array
    {
        $breakdown = [
            'law' => 0,
            'case' => 0,
            'court_decision' => 0,
        ];

        foreach ($documents as $doc) {
            $type = $doc['type'] ?? 'unknown';
            if (isset($breakdown[$type])) {
                $breakdown[$type]++;
            }
        }

        return $breakdown;
    }

    /**
     * Calculate total tokens across all documents
     */
    protected function calculateTotalTokens(array $documents): int
    {
        return array_sum(array_map(fn($doc) => $doc['token_count'] ?? 0, $documents));
    }

    /**
     * Estimate tokens from content (fallback if token_count not available)
     */
    protected function estimateTokens(string $content): int
    {
        return (int) ceil(strlen($content) * self::AVERAGE_TOKENS_PER_CHAR);
    }
}
