<?php

namespace App\Services;

/**
 * Helper class with common graph query patterns for legal RAG system
 */
class GraphQueryHelper
{
    public function __construct(protected GraphDatabaseService $graph)
    {
    }

    /**
     * Find laws that cite specific law
     */
    public function findCitingLaws(string $lawId, int $limit = 20): array
    {
        $query = "MATCH (citing:LawDocument)-[:CITES]->(cited:LawDocument {id: \$lawId})
                  RETURN citing
                  ORDER BY citing.effective_date DESC
                  LIMIT \$limit";

        $result = $this->graph->run($query, ['lawId' => $lawId, 'limit' => $limit]);
        return $result->map(fn($r) => $r->get('citing')->getProperties())->toArray();
    }

    /**
     * Find cases that apply a specific law
     */
    public function findCasesApplyingLaw(string $lawId, int $limit = 20): array
    {
        $query = "MATCH (case:CaseDocument)-[:REFERENCES]->(law:LawDocument {id: \$lawId})
                  RETURN case
                  LIMIT \$limit";

        $result = $this->graph->run($query, ['lawId' => $lawId, 'limit' => $limit]);
        return $result->map(fn($r) => $r->get('case')->getProperties())->toArray();
    }

    /**
     * Find documents with similar tag patterns
     */
    public function findByTagPattern(array $tags, int $minMatches = 2, int $limit = 20): array
    {
        $tagIds = array_map(fn($tag) => 'tag_' . str_replace(['-', ' '], '_', strtolower($tag)), $tags);

        $query = "MATCH (doc)-[:HAS_TAG]->(t:Tag)
                  WHERE t.id IN \$tagIds
                  WITH doc, count(DISTINCT t) as matches
                  WHERE matches >= \$minMatches
                  RETURN doc, matches
                  ORDER BY matches DESC
                  LIMIT \$limit";

        $result = $this->graph->run($query, [
            'tagIds' => $tagIds,
            'minMatches' => $minMatches,
            'limit' => $limit,
        ]);

        return $result->map(fn($r) => [
            'document' => $r->get('doc')->getProperties(),
            'matches' => $r->get('matches'),
        ])->toArray();
    }

    /**
     * Find temporal evolution of laws (amendments, supersedes)
     */
    public function findLawEvolution(string $lawId): array
    {
        $query = "MATCH path = (old:LawDocument)-[:SUPERSEDES|AMENDED_BY*]->(new:LawDocument {id: \$lawId})
                  RETURN nodes(path) as evolution
                  ORDER BY length(path) DESC
                  LIMIT 1";

        $result = $this->graph->run($query, ['lawId' => $lawId]);

        if ($result->count() === 0) {
            return [];
        }

        $nodes = $result->first()->get('evolution');
        return array_map(fn($node) => $node->getProperties(), $nodes);
    }

    /**
     * Find documents by jurisdiction and tags
     */
    public function findByJurisdictionAndTags(string $jurisdiction, array $tags, int $limit = 20): array
    {
        $tagIds = array_map(fn($tag) => 'tag_' . str_replace(['-', ' '], '_', strtolower($tag)), $tags);
        $jurisdictionId = 'jurisdiction_' . $jurisdiction;

        $query = "MATCH (doc)-[:BELONGS_TO_JURISDICTION]->(j:Jurisdiction {id: \$jurisdictionId})
                  MATCH (doc)-[:HAS_TAG]->(t:Tag)
                  WHERE t.id IN \$tagIds
                  RETURN DISTINCT doc
                  LIMIT \$limit";

        $result = $this->graph->run($query, [
            'jurisdictionId' => $jurisdictionId,
            'tagIds' => $tagIds,
            'limit' => $limit,
        ]);

        return $result->map(fn($r) => $r->get('doc')->getProperties())->toArray();
    }

    /**
     * Get keyword co-occurrence network
     */
    public function getKeywordNetwork(string $keyword, int $depth = 2, int $limit = 50): array
    {
        $keywordId = 'keyword_' . md5(strtolower($keyword));

        $query = "MATCH (k:Keyword {id: \$keywordId})<-[:HAS_KEYWORD]-(doc)-[:HAS_KEYWORD]->(related:Keyword)
                  WHERE k <> related
                  WITH related, count(doc) as frequency
                  ORDER BY frequency DESC
                  LIMIT \$limit
                  RETURN related.name as keyword, frequency";

        $result = $this->graph->run($query, [
            'keywordId' => $keywordId,
            'limit' => $limit,
        ]);

        return $result->map(fn($r) => [
            'keyword' => $r->get('keyword'),
            'frequency' => $r->get('frequency'),
        ])->toArray();
    }

    /**
     * Find most influential documents (most cited/referenced)
     */
    public function findInfluentialDocuments(string $nodeType = 'LawDocument', int $limit = 20): array
    {
        $query = "MATCH (doc:$nodeType)<-[r:CITES|REFERENCES]-()
                  WITH doc, count(r) as citations
                  ORDER BY citations DESC
                  LIMIT \$limit
                  RETURN doc, citations";

        $result = $this->graph->run($query, ['limit' => $limit]);

        return $result->map(fn($r) => [
            'document' => $r->get('doc')->getProperties(),
            'citations' => $r->get('citations'),
        ])->toArray();
    }

    /**
     * Find documents in a specific topic cluster
     */
    public function findTopicCluster(string $topicTag, int $depth = 2): array
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($topicTag));

        $query = "MATCH (t:Tag {id: \$tagId})
                  OPTIONAL MATCH (t)<-[:PARENT_TAG*0..$depth]-(childTag:Tag)
                  WITH collect(DISTINCT t) + collect(DISTINCT childTag) as tags
                  UNWIND tags as tag
                  MATCH (doc)-[:HAS_TAG]->(tag)
                  RETURN DISTINCT doc";

        $result = $this->graph->run($query, ['tagId' => $tagId]);
        return $result->map(fn($r) => $r->get('doc')->getProperties())->toArray();
    }

    /**
     * Find contradicting or supporting documents
     */
    public function findRelatedOpinions(string $docId, string $relationType = 'SUPPORTS'): array
    {
        $query = "MATCH (doc {id: \$docId})-[r:$relationType]->(related)
                  RETURN related, r.strength as strength, r.created_at as created_at
                  ORDER BY strength DESC";

        $result = $this->graph->run($query, ['docId' => $docId]);

        return $result->map(fn($r) => [
            'document' => $r->get('related')->getProperties(),
            'strength' => $r->get('strength'),
            'created_at' => $r->get('created_at'),
        ])->toArray();
    }

    /**
     * Recommend similar documents based on multiple factors
     */
    public function recommendDocuments(string $docId, int $limit = 10): array
    {
        $query = "MATCH (doc {id: \$docId})

                  // Find similar by embeddings
                  OPTIONAL MATCH (doc)-[s:SIMILAR_TO]->(similar1)

                  // Find related by shared tags
                  OPTIONAL MATCH (doc)-[:HAS_TAG]->(t:Tag)<-[:HAS_TAG]-(similar2)

                  // Find related by shared keywords
                  OPTIONAL MATCH (doc)-[:HAS_KEYWORD]->(k:Keyword)<-[:HAS_KEYWORD]-(similar3)

                  WITH similar1, similar2, similar3, s.similarity as embedding_score

                  // Combine all similar documents
                  WITH collect(DISTINCT similar1) + collect(DISTINCT similar2) + collect(DISTINCT similar3) as candidates,
                       collect(DISTINCT embedding_score) as scores

                  UNWIND candidates as candidate
                  WHERE candidate IS NOT NULL

                  RETURN DISTINCT candidate,
                         CASE WHEN candidate IN collect(similar1) THEN 1.0 ELSE 0.5 END as score
                  ORDER BY score DESC
                  LIMIT \$limit";

        $result = $this->graph->run($query, ['docId' => $docId, 'limit' => $limit]);

        return $result->map(fn($r) => [
            'document' => $r->get('candidate')->getProperties(),
            'score' => $r->get('score'),
        ])->toArray();
    }
}

