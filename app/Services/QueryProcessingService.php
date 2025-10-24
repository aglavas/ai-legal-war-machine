<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes user queries for optimal retrieval
 * Includes query rewriting, expansion, and intent classification
 */
class QueryProcessingService
{
    public function __construct(
        protected OpenAIService $openai,
        protected HrLegalCitationsDetector $citationDetector
    ) {
    }

    /**
     * Process a query for optimal retrieval
     */
    public function process(string $query, string $agentType = 'general'): array
    {
        // Extract entities using robust Croatian legal citations detector
        $entities = $this->citationDetector->extract($query);

        return [
            'original' => $query,
            'cleaned' => $this->cleanQuery($query),
            'rewritten' => $this->rewriteQuery($query, $agentType),
            'intent' => $this->classifyIntent($query),
            'entities' => $entities,
            'keywords' => $this->citationDetector->extractKeywords($query),
            'has_specific_refs' => $entities['has_specific_refs'] ?? false,
        ];
    }

    /**
     * Clean and normalize the query
     */
    protected function cleanQuery(string $query): string
    {
        // Normalize whitespace
        $query = preg_replace('/\s+/u', ' ', $query);

        // Remove excessive punctuation
        $query = preg_replace('/[!?.]+/u', '.', $query);

        return trim($query);
    }

    /**
     * Rewrite query using AI for better retrieval
     * This expands the query with legal terminology and synonyms
     */
    protected function rewriteQuery(string $query, string $agentType): ?string
    {
        try {
            $systemPrompt = $this->getRewriteSystemPrompt($agentType);

            $response = $this->openai->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query],
            ], null, [
                'temperature' => 0.3,
                'max_tokens' => 150,
            ]);

            $rewritten = trim($response['choices'][0]['message']['content'] ?? '');

            return !empty($rewritten) ? $rewritten : null;
        } catch (Throwable $e) {
            Log::debug('query_processing.rewrite_failed', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
            return null;
        }
    }

    /**
     * Get system prompt for query rewriting based on agent type
     */
    protected function getRewriteSystemPrompt(string $agentType): string
    {
        $base = 'Rewrite this legal query to be more specific and include relevant legal terminology. ';
        $base .= 'Include Croatian legal terms if applicable. Keep it concise (1-2 sentences). ';

        return match ($agentType) {
            'law' => $base . 'Focus on Croatian laws, regulations, and statutory provisions. Include law names and article numbers if mentioned.',
            'court_decision' => $base . 'Focus on court rulings, precedents, and case law. Include court names and case numbers if mentioned.',
            'case_analysis' => $base . 'Focus on legal issues, arguments, and procedural aspects. Include relevant causes of action.',
            default => $base . 'Include both legal terminology and common language explanations.',
        };
    }

    /**
     * Classify the intent of the query
     */
    protected function classifyIntent(string $query): string
    {
        $lowerQuery = mb_strtolower($query);

        // Specific law lookup
        if (preg_match('/\b(zakon|nn|članak|čl\.|stavak|st\.)/iu', $query)) {
            return 'law_lookup';
        }

        // Case number lookup
        if (preg_match('/[A-Z]{1,3}-?\d+\/\d{2,4}/i', $query)) {
            return 'case_lookup';
        }

        // Definition/explanation
        if (preg_match('/\b(što je|defini|objasni|znači|meaning|define)\b/iu', $query)) {
            return 'definition';
        }

        // Procedure/how-to
        if (preg_match('/\b(kako|postupak|procedure|proces|koraci|steps)\b/iu', $query)) {
            return 'procedure';
        }

        // Comparison
        if (preg_match('/\b(razlika|difference|compare|usporedba|vs)\b/iu', $query)) {
            return 'comparison';
        }

        // Recent changes
        if (preg_match('/\b(novi|nova|izmjena|promjena|recent|latest|updated)\b/iu', $query)) {
            return 'recent_changes';
        }

        // General research
        return 'general_research';
    }

    /**
     * Generate search queries from processed query
     * Returns multiple query variants for comprehensive retrieval
     */
    public function generateSearchQueries(array $processedQuery): array
    {
        $queries = [];

        // Original cleaned query
        $queries[] = [
            'text' => $processedQuery['cleaned'],
            'boost' => 1.0,
            'type' => 'original',
        ];

        // Rewritten query (if available)
        if (!empty($processedQuery['rewritten'])) {
            $queries[] = [
                'text' => $processedQuery['rewritten'],
                'boost' => 1.2,
                'type' => 'rewritten',
            ];
        }

        // Keyword-based query
        if (!empty($processedQuery['keywords'])) {
            $queries[] = [
                'text' => implode(' ', $processedQuery['keywords']),
                'boost' => 0.8,
                'type' => 'keywords',
            ];
        }

        // Entity-based queries
        $entities = $processedQuery['entities'];

        // Law names
        if (!empty($entities['laws'])) {
            foreach ($entities['laws'] as $law) {
                $queries[] = [
                    'text' => $law['value'],
                    'boost' => 1.5,
                    'type' => 'law_reference',
                    'entity' => $law,
                ];
            }
        }

        // Case numbers
        if (!empty($entities['case_numbers'])) {
            foreach ($entities['case_numbers'] as $case) {
                $queries[] = [
                    'text' => $case['full'],
                    'boost' => 2.0, // Highest boost for exact case references
                    'type' => 'case_reference',
                    'entity' => $case,
                ];
            }
        }

        return $queries;
    }
}
