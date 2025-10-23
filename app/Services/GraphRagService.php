<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GraphRagService
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected TaggingService $tagging
    ) {
    }

    /**
     * Sync a law document to graph database
     */
    public function syncLaw(string $lawId): void
    {
        $law = DB::table('laws')->where('id', $lawId)->first();

        if (!$law) {
            return;
        }

        // Create law document node
        $this->graph->upsertNode('LawDocument', $law->id, [
            'doc_id' => $law->doc_id,
            'title' => $law->title,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'chunk_index' => $law->chunk_index,
            'content_hash' => $law->content_hash,
            'effective_date' => $law->effective_date,
            'promulgation_date' => $law->promulgation_date,
        ]);

        // Create jurisdiction node if exists
        if ($law->jurisdiction) {
            $this->graph->upsertNode('Jurisdiction', 'jurisdiction_' . $law->jurisdiction, [
                'name' => $law->jurisdiction,
            ]);

            $this->graph->createRelationship(
                'LawDocument',
                $law->id,
                'BELONGS_TO_JURISDICTION',
                'Jurisdiction',
                'jurisdiction_' . $law->jurisdiction
            );
        }

        // Auto-tag the law
        $metadata = json_decode($law->metadata ?? '[]', true);
        $this->tagging->autoTag('LawDocument', $law->id, $law->content, array_merge($metadata, [
            'jurisdiction' => $law->jurisdiction,
            'law_number' => $law->law_number,
        ]));

        // Create keyword relationships
        $this->extractAndLinkKeywords('LawDocument', $law->id, $law->content);

        // Find and create similarity relationships
        $this->createSimilarityRelationships('LawDocument', $law->id, $law->embedding_vector ?? null);
    }

    /**
     * Sync a case document to graph database
     */
    public function syncCase(string $caseDocId): void
    {
        $caseDoc = DB::table('cases_documents')->where('id', $caseDocId)->first();

        if (!$caseDoc) {
            return;
        }

        // Create case document node
        $this->graph->upsertNode('CaseDocument', $caseDoc->id, [
            'case_id' => $caseDoc->case_id,
            'doc_id' => $caseDoc->doc_id,
            'title' => $caseDoc->title,
            'category' => $caseDoc->category,
            'language' => $caseDoc->language,
            'chunk_index' => $caseDoc->chunk_index,
            'content_hash' => $caseDoc->content_hash,
            'source' => $caseDoc->source,
        ]);

        // Auto-tag the case
        $metadata = json_decode($caseDoc->metadata ?? '[]', true);
        $this->tagging->autoTag('CaseDocument', $caseDoc->id, $caseDoc->content, $metadata);

        // Create keyword relationships
        $this->extractAndLinkKeywords('CaseDocument', $caseDoc->id, $caseDoc->content);

        // Find and create similarity relationships
        $this->createSimilarityRelationships('CaseDocument', $caseDoc->id, $caseDoc->embedding_vector ?? null);
    }

    /**
     * Extract keywords and create keyword nodes with relationships
     */
    protected function extractAndLinkKeywords(string $nodeLabel, string $nodeId, string $content): void
    {
        // Simple keyword extraction (can be enhanced with NLP)
        $keywords = $this->extractKeywords($content);

        foreach ($keywords as $keyword => $weight) {
            $keywordId = 'keyword_' . md5(strtolower($keyword));

            $this->graph->upsertNode('Keyword', $keywordId, [
                'name' => $keyword,
                'normalized' => mb_strtolower($keyword),
            ]);

            $this->graph->createRelationship(
                $nodeLabel,
                $nodeId,
                'HAS_KEYWORD',
                'Keyword',
                $keywordId,
                ['weight' => $weight]
            );
        }
    }

    /**
     * Extract keywords from content with legal term boosting
     */
    protected function extractKeywords(string $content, int $maxKeywords = 10): array
    {
        // Croatian legal terms with boost weights
        $legalTerms = [
            // Core legal concepts
            'ugovor' => 2.5,        // contract
            'obveza' => 2.5,        // obligation
            'pravo' => 2.5,         // right/law
            'zakon' => 3.0,         // law/statute
            'odredba' => 2.5,       // provision
            'postupak' => 2.5,      // procedure
            'naknada' => 2.0,       // compensation
            'presuda' => 2.5,       // judgment/verdict
            'odluka' => 2.5,        // decision
            'rješenje' => 2.0,      // resolution

            // Legal entities and parties
            'tužitelj' => 2.0,      // plaintiff
            'tuženik' => 2.0,       // defendant
            'stranka' => 2.0,       // party
            'sud' => 2.5,           // court
            'sudac' => 2.0,         // judge
            'svjedok' => 2.0,       // witness
            'odvjetnik' => 2.0,     // lawyer

            // Legal processes
            'žalba' => 2.0,         // appeal
            'tužba' => 2.5,         // lawsuit
            'parnica' => 2.0,       // litigation
            'izvršenje' => 2.0,     // execution/enforcement
            'dokazivanje' => 2.0,   // proving/evidence
            'saslušanje' => 2.0,    // hearing
            'pretres' => 2.0,       // trial

            // Legal effects and outcomes
            'ništavost' => 2.0,     // nullity
            'poništenje' => 2.0,    // annulment
            'razvrgnuće' => 2.0,    // dissolution
            'prekid' => 1.8,        // termination
            'prestanak' => 1.8,     // cessation
            'stupanje' => 1.8,      // coming into force

            // Specific legal areas
            'kazneno' => 2.0,       // criminal
            'građansko' => 2.0,     // civil
            'upravno' => 2.0,       // administrative
            'trgovačko' => 2.0,     // commercial
            'radno' => 1.8,         // labor
            'obiteljsko' => 1.8,    // family

            // Important legal modifiers
            'zakonit' => 2.0,       // lawful
            'nezakonit' => 2.0,     // unlawful
            'valjan' => 1.8,        // valid
            'ništav' => 2.0,        // void
            'pravomočan' => 2.0,    // final/legally binding
            'izvršan' => 1.8,       // executable

            // Legal documents and norms
            'uredba' => 2.0,        // ordinance/regulation
            'pravilnik' => 2.0,     // rulebook
            'statut' => 2.0,        // statute
            'protokol' => 1.8,      // protocol
            'sporazum' => 2.0,      // agreement
            'konvencija' => 2.0,    // convention
        ];

        // Remove common Croatian stopwords
        $stopwords = ['je', 'su', 'biti', 'ima', 'da', 'za', 'na', 'u', 'i', 'ili', 'te', 'se', 'by', 'the', 'of', 'and', 'to', 'a', 'in', 'koji', 'koja', 'koje', 'ovaj', 'taj'];

        // Tokenize and clean
        $words = preg_split('/\s+/', mb_strtolower($content));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $stopwords));

        // Count frequencies
        $frequencies = array_count_values($words);

        // Apply legal term boosting
        foreach ($frequencies as $word => $freq) {
            if (isset($legalTerms[$word])) {
                $frequencies[$word] = $freq * $legalTerms[$word];
            }
        }

        arsort($frequencies);

        // Get top keywords and normalize weights
        $topKeywords = array_slice($frequencies, 0, $maxKeywords, true);
        $maxFreq = max($topKeywords ?: [1]);

        return array_map(fn($freq) => round($freq / $maxFreq, 2), $topKeywords);
    }

    /**
     * Create similarity relationships based on vector embeddings
     */
    protected function createSimilarityRelationships(string $nodeLabel, string $nodeId, ?string $embeddingVector): void
    {
        if (!$embeddingVector || !config('neo4j.sync.enabled')) {
            return;
        }

        $vector = json_decode($embeddingVector, true);
        if (!is_array($vector)) {
            return;
        }

        // Find similar documents using vector similarity from relational DB
        $threshold = config('neo4j.similarity.threshold', 0.85);
        $limit = config('neo4j.similarity.max_relationships', 10);

        $table = $nodeLabel === 'LawDocument' ? 'laws' : 'cases_documents';

        // Get similar documents (simplified - in production use proper vector similarity)
        $similar = DB::table($table)
            ->where('id', '!=', $nodeId)
            ->limit($limit)
            ->get();

        foreach ($similar as $doc) {
            $docVector = json_decode($doc->embedding_vector ?? '[]', true);
            if (!is_array($docVector)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($vector, $docVector);

            if ($similarity >= $threshold) {
                $this->graph->createRelationship(
                    $nodeLabel,
                    $nodeId,
                    'SIMILAR_TO',
                    $nodeLabel,
                    $doc->id,
                    [
                        'similarity' => round($similarity, 4),
                        'computed_at' => now()->toIso8601String(),
                    ]
                );
            }
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
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
     * Enhanced RAG query using graph relationships
     */
    public function enhancedQuery(string $query, string $contextType = 'both', int $limit = 10): array
    {
        $results = [
            'direct_matches' => [],
            'related_via_tags' => [],
            'related_via_keywords' => [],
            'similar_documents' => [],
            'graph_context' => [],
        ];

        // Extract keywords from query
        $queryKeywords = $this->extractKeywords($query, 5);

        // Find documents by keywords
        foreach (array_keys($queryKeywords) as $keyword) {
            $keywordId = 'keyword_' . md5(strtolower($keyword));

            $nodeTypes = match($contextType) {
                'law' => ['LawDocument'],
                'case' => ['CaseDocument'],
                default => ['LawDocument', 'CaseDocument'],
            };

            foreach ($nodeTypes as $nodeType) {
                try {
                    $cypher = "MATCH (n:$nodeType)-[r:HAS_KEYWORD]->(k:Keyword {id: \$keywordId})
                               RETURN n, r.weight as weight
                               ORDER BY weight DESC
                               LIMIT \$limit";

                    $result = $this->graph->run($cypher, [
                        'keywordId' => $keywordId,
                        'limit' => $limit,
                    ]);

                    foreach ($result as $record) {
                        $results['related_via_keywords'][] = [
                            'node' => $record->get('n')->getProperties(),
                            'weight' => $record->get('weight'),
                            'keyword' => $keyword,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Graph query failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $results;
    }

    /**
     * Get graph context for a document (related documents, tags, keywords)
     */
    public function getGraphContext(string $nodeLabel, string $nodeId, int $depth = 2): array
    {
        $context = [
            'node' => null,
        ];

        // Get the node with its relationships
        $nodeData = $this->graph->getNodeWithRelationships($nodeLabel, $nodeId);
        if ($nodeData) {
            $context['node'] = $nodeData['node'];
        }

        // Get tags
        $context['tags'] = $this->tagging->getNodeTags($nodeLabel, $nodeId);

        // Get keywords
        $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:HAS_KEYWORD]->(k:Keyword)
                   RETURN k.name as keyword, r.weight as weight
                   ORDER BY weight DESC";

        $result = $this->graph->run($cypher, ['id' => $nodeId]);
        $context['keywords'] = $result->map(fn($r) => [
            'keyword' => $r->get('keyword'),
            'weight' => $r->get('weight'),
        ])->toArray();

        // Get similar documents
        $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:SIMILAR_TO]->(similar)
                   RETURN similar, r.similarity as similarity
                   ORDER BY similarity DESC
                   LIMIT 10";

        $result = $this->graph->run($cypher, ['id' => $nodeId]);
        $context['similar'] = $result->map(fn($r) => [
            'document' => $r->get('similar')->getProperties(),
            'similarity' => $r->get('similarity'),
        ])->toArray();

        // Get related documents through shared tags
        $cypher = "MATCH (n:$nodeLabel {id: \$id})-[:HAS_TAG]->(t:Tag)<-[:HAS_TAG]-(related)
                   WHERE n <> related
                   RETURN DISTINCT related, count(t) as shared_tags
                   ORDER BY shared_tags DESC
                   LIMIT 10";

        $result = $this->graph->run($cypher, ['id' => $nodeId]);
        $context['related'] = $result->map(fn($r) => [
            'document' => $r->get('related')->getProperties(),
            'shared_tags' => $r->get('shared_tags'),
        ])->toArray();

        return $context;
    }

    /**
     * Batch sync all laws to graph database
     */
    public function syncAllLaws(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('laws')
            ->orderBy('id')
            ->chunk($batchSize, function ($laws) use (&$synced, &$errors) {
                foreach ($laws as $law) {
                    try {
                        $this->syncLaw($law->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync law to graph', [
                            'law_id' => $law->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Batch sync all cases to graph database
     */
    public function syncAllCases(): array
    {
        $batchSize = config('neo4j.sync.batch_size', 100);
        $synced = 0;
        $errors = 0;

        DB::table('cases_documents')
            ->orderBy('id')
            ->chunk($batchSize, function ($cases) use (&$synced, &$errors) {
                foreach ($cases as $case) {
                    try {
                        $this->syncCase($case->id);
                        $synced++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to sync case to graph', [
                            'case_id' => $case->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['synced' => $synced, 'errors' => $errors];
    }
}
