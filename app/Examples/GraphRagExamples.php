<?php

/**
 * Example Usage of Graph Database RAG System
 *
 * This file demonstrates how to use the graph database integration
 * with the existing RAG system for enhanced legal document retrieval.
 */

namespace App\Examples;

use App\Services\GraphRagService;
use App\Services\GraphQueryHelper;
use App\Services\TaggingService;

class GraphRagExamples
{
    public function __construct(
        protected GraphRagService $graphRag,
        protected GraphQueryHelper $queryHelper,
        protected TaggingService $tagging
    ) {
    }

    /**
     * Example 1: Enhanced document search with graph context
     */
    public function enhancedSearch(string $query): array
    {
        // Perform graph-enhanced query
        $results = $this->graphRag->enhancedQuery($query, 'both', 10);

        // Results include:
        // - Documents matching keywords from graph
        // - Documents with similar tags
        // - Similar documents based on embeddings

        return $results;
    }

    /**
     * Example 2: Get comprehensive context for a law
     */
    public function getLawContext(string $lawId): array
    {
        $context = $this->graphRag->getGraphContext('LawDocument', $lawId);

        // Returns:
        // - The law node properties
        // - All tags applied to the law
        // - Weighted keywords
        // - Similar laws (based on embeddings)
        // - Related laws (shared tags)

        return [
            'document' => $context['node'],
            'tags' => $context['tags'],
            'keywords' => $context['keywords'],
            'similar_laws' => $context['similar'],
            'related_laws' => $context['related'],
        ];
    }

    /**
     * Example 3: Find laws by topic with tag hierarchy
     */
    public function findLawsByTopic(string $topic): array
    {
        // Find all documents in this topic cluster (including sub-tags)
        return $this->queryHelper->findTopicCluster($topic);
    }

    /**
     * Example 4: Find cases that apply a specific law
     */
    public function findRelevantCases(string $lawId): array
    {
        return $this->queryHelper->findCasesApplyingLaw($lawId);
    }

    /**
     * Example 5: Get document recommendations
     */
    public function getRecommendations(string $docId): array
    {
        // Get recommendations based on:
        // - Vector similarity
        // - Shared tags
        // - Shared keywords

        return $this->queryHelper->recommendDocuments($docId, 10);
    }

    /**
     * Example 6: Manual tagging
     */
    public function tagDocument(string $nodeLabel, string $nodeId, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tagging->applyTag($nodeLabel, $nodeId, $tag);
        }
    }

    /**
     * Example 7: Find documents by multiple criteria
     */
    public function complexSearch(string $jurisdiction, array $tags): array
    {
        return $this->queryHelper->findByJurisdictionAndTags($jurisdiction, $tags);
    }

    /**
     * Example 8: Analyze keyword relationships
     */
    public function analyzeKeyword(string $keyword): array
    {
        // Get keywords that often appear with this keyword
        $network = $this->queryHelper->getKeywordNetwork($keyword);

        return [
            'keyword' => $keyword,
            'related_keywords' => $network,
        ];
    }

    /**
     * Example 9: Find most influential laws
     */
    public function findInfluentialLaws(): array
    {
        // Find laws that are most cited/referenced
        return $this->queryHelper->findInfluentialDocuments('LawDocument', 20);
    }

    /**
     * Example 10: Complete RAG workflow
     */
    public function completeRagWorkflow(string $userQuery): array
    {
        // Step 1: Graph-enhanced query
        $graphResults = $this->graphRag->enhancedQuery($userQuery, 'both', 5);

        // Step 2: Get context for top results
        $enrichedResults = [];
        foreach ($graphResults['related_via_keywords'] ?? [] as $result) {
            $node = $result['node'];
            $nodeId = $node['id'];
            $nodeLabel = isset($node['law_number']) ? 'LawDocument' : 'CaseDocument';

            // Get full graph context
            $context = $this->graphRag->getGraphContext($nodeLabel, $nodeId);

            $enrichedResults[] = [
                'document' => $node,
                'relevance_score' => $result['weight'],
                'tags' => $context['tags'],
                'similar_docs' => $context['similar'],
                'related_docs' => $context['related'],
            ];
        }

        return [
            'query' => $userQuery,
            'results' => $enrichedResults,
            'graph_stats' => [
                'keywords_matched' => count($graphResults['related_via_keywords'] ?? []),
                'tags_matched' => count($graphResults['related_via_tags'] ?? []),
            ],
        ];
    }
}

