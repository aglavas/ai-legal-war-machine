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

        // Extract and create citation relationships
        $this->extractAndCreateCitations('LawDocument', $law->id, $law->content);

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

        // Extract and create citation relationships (cases can cite laws and reference other cases)
        $this->extractAndCreateCitations('CaseDocument', $caseDoc->id, $caseDoc->content);

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
     * Extract keywords from content (basic implementation)
     */
    protected function extractKeywords(string $content, int $maxKeywords = 10): array
    {
        // Remove common Croatian stopwords
        $stopwords = ['je', 'su', 'biti', 'ima', 'da', 'za', 'na', 'u', 'i', 'ili', 'te', 'se', 'by', 'the', 'of', 'and', 'to', 'a', 'in'];

        // Tokenize and clean
        $words = preg_split('/\s+/', mb_strtolower($content));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $stopwords));

        // Count frequencies
        $frequencies = array_count_values($words);
        arsort($frequencies);

        // Get top keywords and normalize weights
        $topKeywords = array_slice($frequencies, 0, $maxKeywords, true);
        $maxFreq = max($topKeywords ?: [1]);

        return array_map(fn($freq) => round($freq / $maxFreq, 2), $topKeywords);
    }

    /**
     * Extract citations from content and create relationships
     */
    protected function extractAndCreateCitations(string $nodeLabel, string $nodeId, string $content): void
    {
        // Croatian legal citation patterns
        $citations = [];

        // Pattern 1: NN citations - "NN 123/20", "Narodne novine 45/2021"
        preg_match_all('/(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches);
        foreach ($matches[1] as $lawNumber) {
            $citations[] = [
                'type' => 'law_number',
                'value' => $lawNumber,
            ];
        }

        // Pattern 2: Article references with law numbers - "članak 5. Zakona (NN 123/20)"
        preg_match_all('/(?:članak|čl\.?)\s+(\d+)(?:\.|,)?\s*(?:Zakona?\s+)?(?:\()?(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'article_reference',
                'article' => $match[1],
                'law_number' => $match[2],
            ];
        }

        // Pattern 3: Law names with citations - "Zakon o ...  (NN 123/20)"
        preg_match_all('/Zakon\s+o\s+([^\(]{5,100})\s*\((?:NN|Narodne\s+novine)\s+(\d+\/\d+)\)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'named_law',
                'name' => trim($match[1]),
                'law_number' => $match[2],
            ];
        }

        // Pattern 4: Court decisions/case references - "odluka broj: XYZ-123/2020"
        preg_match_all('/(?:odluka|presuda|rješenje)\s+(?:broj:?\s+)?([A-Z]+-?\d+\/\d+(?:-\d+)?)/iu', $content, $matches);
        foreach ($matches[1] as $caseNumber) {
            $citations[] = [
                'type' => 'case_reference',
                'value' => $caseNumber,
            ];
        }

        // Process citations and create relationships
        foreach ($citations as $citation) {
            try {
                if (isset($citation['law_number'])) {
                    // Find the referenced law in database
                    $referencedLaw = DB::table('laws')
                        ->where('law_number', $citation['law_number'])
                        ->first();

                    if ($referencedLaw) {
                        // Ensure the referenced law node exists
                        $this->graph->upsertNode('LawDocument', $referencedLaw->id, [
                            'doc_id' => $referencedLaw->doc_id,
                            'title' => $referencedLaw->title,
                            'law_number' => $referencedLaw->law_number,
                        ]);

                        // Create CITES relationship
                        $relationshipProps = [
                            'citation_type' => $citation['type'],
                            'created_at' => now()->toIso8601String(),
                        ];

                        // Add article number if available
                        if (isset($citation['article'])) {
                            $relationshipProps['article'] = $citation['article'];
                        }

                        $this->graph->createRelationship(
                            $nodeLabel,
                            $nodeId,
                            'CITES',
                            'LawDocument',
                            $referencedLaw->id,
                            $relationshipProps
                        );
                    }
                } elseif (isset($citation['value']) && $citation['type'] === 'case_reference') {
                    // Find the referenced case in database
                    $referencedCase = DB::table('cases_documents')
                        ->where('doc_id', 'LIKE', '%' . $citation['value'] . '%')
                        ->first();

                    if ($referencedCase) {
                        // Ensure the referenced case node exists
                        $this->graph->upsertNode('CaseDocument', $referencedCase->id, [
                            'case_id' => $referencedCase->case_id,
                            'doc_id' => $referencedCase->doc_id,
                            'title' => $referencedCase->title,
                        ]);

                        // Create REFERENCES relationship
                        $this->graph->createRelationship(
                            $nodeLabel,
                            $nodeId,
                            'REFERENCES',
                            'CaseDocument',
                            $referencedCase->id,
                            [
                                'citation_type' => 'case_reference',
                                'created_at' => now()->toIso8601String(),
                            ]
                        );
                    }
                } elseif (isset($citation['value']) && $citation['type'] === 'law_number') {
                    // Direct law number citation
                    $referencedLaw = DB::table('laws')
                        ->where('law_number', $citation['value'])
                        ->first();

                    if ($referencedLaw) {
                        // Ensure the referenced law node exists
                        $this->graph->upsertNode('LawDocument', $referencedLaw->id, [
                            'doc_id' => $referencedLaw->doc_id,
                            'title' => $referencedLaw->title,
                            'law_number' => $referencedLaw->law_number,
                        ]);

                        // Create CITES relationship
                        $this->graph->createRelationship(
                            $nodeLabel,
                            $nodeId,
                            'CITES',
                            'LawDocument',
                            $referencedLaw->id,
                            [
                                'citation_type' => 'law_number',
                                'created_at' => now()->toIso8601String(),
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to create citation relationship', [
                    'citation' => $citation,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Citation extraction completed', [
            'node_label' => $nodeLabel,
            'node_id' => $nodeId,
            'citations_found' => count($citations),
        ]);
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

        // Get cited laws (if any)
        $context['cited_laws'] = $this->getCitedLaws($nodeLabel, $nodeId);

        // Get citing documents (if this is a law)
        if ($nodeLabel === 'LawDocument') {
            $context['citing_documents'] = $this->getCitingDocuments($nodeId);
        }

        // Get referenced cases (if any)
        $context['referenced_cases'] = $this->getReferencedCases($nodeLabel, $nodeId);

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

    /**
     * Get all laws cited by a document
     */
    public function getCitedLaws(string $nodeLabel, string $nodeId): array
    {
        try {
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:CITES]->(law:LawDocument)
                       RETURN law, r.citation_type as citation_type, r.article as article
                       ORDER BY law.law_number";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);

            return $result->map(fn($r) => [
                'law' => $r->get('law')->getProperties(),
                'citation_type' => $r->get('citation_type'),
                'article' => $r->get('article'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get cited laws', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get all documents that cite a specific law
     */
    public function getCitingDocuments(string $lawId): array
    {
        try {
            $cypher = "MATCH (doc)-[r:CITES]->(law:LawDocument {id: \$lawId})
                       RETURN doc, labels(doc)[0] as docType, r.citation_type as citation_type, r.article as article
                       ORDER BY doc.title";

            $result = $this->graph->run($cypher, ['lawId' => $lawId]);

            return $result->map(fn($r) => [
                'document' => $r->get('doc')->getProperties(),
                'document_type' => $r->get('docType'),
                'citation_type' => $r->get('citation_type'),
                'article' => $r->get('article'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get citing documents', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get all case references from a document
     */
    public function getReferencedCases(string $nodeLabel, string $nodeId): array
    {
        try {
            $cypher = "MATCH (n:$nodeLabel {id: \$id})-[r:REFERENCES]->(case:CaseDocument)
                       RETURN case, r.citation_type as citation_type
                       ORDER BY case.doc_id";

            $result = $this->graph->run($cypher, ['id' => $nodeId]);

            return $result->map(fn($r) => [
                'case' => $r->get('case')->getProperties(),
                'citation_type' => $r->get('citation_type'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get referenced cases', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get citation network for a law (useful for visualization)
     * Returns laws that cite this law, and laws cited by those laws
     */
    public function getCitationNetwork(string $lawId, int $depth = 2): array
    {
        try {
            $cypher = "MATCH path = (citing)-[:CITES*1..$depth]->(law:LawDocument {id: \$lawId})
                       WITH citing, law, relationships(path) as rels
                       RETURN DISTINCT citing, law, rels
                       LIMIT 50";

            $result = $this->graph->run($cypher, ['lawId' => $lawId, 'depth' => $depth]);

            $network = [
                'center' => null,
                'citing' => [],
                'relationships' => [],
            ];

            foreach ($result as $record) {
                $network['center'] = $record->get('law')->getProperties();
                $citingDoc = $record->get('citing')->getProperties();
                $network['citing'][] = $citingDoc;
            }

            return $network;
        } catch (\Exception $e) {
            Log::error('Failed to get citation network', ['error' => $e->getMessage()]);
            return ['center' => null, 'citing' => [], 'relationships' => []];
        }
    }

    /**
     * Get most cited laws (useful for finding important/foundational laws)
     */
    public function getMostCitedLaws(int $limit = 20): array
    {
        try {
            $cypher = "MATCH (doc)-[:CITES]->(law:LawDocument)
                       WITH law, count(doc) as citation_count
                       RETURN law, citation_count
                       ORDER BY citation_count DESC
                       LIMIT \$limit";

            $result = $this->graph->run($cypher, ['limit' => $limit]);

            return $result->map(fn($r) => [
                'law' => $r->get('law')->getProperties(),
                'citation_count' => $r->get('citation_count'),
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get most cited laws', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
