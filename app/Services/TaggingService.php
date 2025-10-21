<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TaggingService
{
    public function __construct(protected GraphDatabaseService $graph)
    {
    }

    /**
     * Hierarchical tag categories for legal content
     */
    protected array $tagHierarchy = [
        'legal_area' => [
            'civil_law' => ['contracts', 'property', 'family', 'inheritance', 'torts'],
            'criminal_law' => ['felonies', 'misdemeanors', 'procedure', 'penalties'],
            'administrative_law' => ['public_administration', 'licensing', 'regulations'],
            'constitutional_law' => ['fundamental_rights', 'state_organization', 'judicial_review'],
            'labor_law' => ['employment', 'collective_bargaining', 'workplace_safety'],
            'commercial_law' => ['companies', 'banking', 'insolvency', 'competition'],
        ],
        'procedure' => [
            'litigation' => ['civil_procedure', 'criminal_procedure', 'administrative_procedure'],
            'alternative_dispute_resolution' => ['mediation', 'arbitration', 'conciliation'],
        ],
        'jurisdiction' => [
            'croatia' => ['national', 'regional', 'local'],
            'european_union' => ['eu_law', 'ecj_cases', 'directives', 'regulations'],
            'international' => ['treaties', 'conventions', 'bilateral_agreements'],
        ],
        'document_type' => [
            'primary_legislation' => ['constitution', 'laws', 'ordinances'],
            'secondary_legislation' => ['regulations', 'bylaws', 'decrees'],
            'case_law' => ['supreme_court', 'appellate_court', 'first_instance'],
        ],
        'topic' => [
            'human_rights' => ['privacy', 'equality', 'freedom_of_expression'],
            'economic_rights' => ['property_rights', 'business_freedom', 'taxation'],
            'environmental' => ['pollution', 'conservation', 'climate'],
            'digital' => ['data_protection', 'cybersecurity', 'e_commerce'],
        ],
    ];

    /**
     * Initialize tag hierarchy in graph database
     */
    public function initializeTagHierarchy(): void
    {
        foreach ($this->tagHierarchy as $category => $subcategories) {
            $this->createTagCategory($category, $subcategories);
        }
    }

    /**
     * Create a hierarchical tag category
     */
    protected function createTagCategory(string $category, array $subcategories, ?string $parentId = null): void
    {
        $categoryId = 'tag_' . $category;

        // Create category tag node
        $this->graph->upsertNode('Tag', $categoryId, [
            'name' => $category,
            'category' => $category,
            'level' => $parentId ? 2 : 1,
            'slug' => str_replace('_', '-', $category),
        ]);

        // Link to parent if exists
        if ($parentId) {
            $this->graph->createRelationship(
                'Tag',
                $categoryId,
                'PARENT_TAG',
                'Tag',
                $parentId
            );
        }

        // Process subcategories
        foreach ($subcategories as $key => $value) {
            if (is_array($value)) {
                // Nested category
                $this->createTagCategory($key, $value, $categoryId);
            } else {
                // Leaf tag
                $tagId = 'tag_' . $value;
                $this->graph->upsertNode('Tag', $tagId, [
                    'name' => $value,
                    'category' => $category,
                    'level' => 3,
                    'slug' => str_replace('_', '-', $value),
                ]);

                $this->graph->createRelationship(
                    'Tag',
                    $tagId,
                    'PARENT_TAG',
                    'Tag',
                    $categoryId
                );
            }
        }
    }

    /**
     * Extract and apply tags to a node based on content and metadata
     */
    public function autoTag(string $nodeLabel, string $nodeId, string $content, array $metadata = []): array
    {
        $tags = [];

        // Extract tags from metadata
        if (isset($metadata['tags']) && is_array($metadata['tags'])) {
            $tags = array_merge($tags, $metadata['tags']);
        }

        // Analyze content for keywords
        $extractedTags = $this->extractTagsFromContent($content, $metadata);
        $tags = array_merge($tags, $extractedTags);

        // Remove duplicates and normalize
        $tags = array_unique(array_map('strtolower', $tags));

        // Apply tags to node
        foreach ($tags as $tag) {
            $this->applyTag($nodeLabel, $nodeId, $tag);
        }

        return $tags;
    }

    /**
     * Extract tags from content using keyword matching and patterns
     */
    protected function extractTagsFromContent(string $content, array $metadata): array
    {
        $tags = [];
        $content = mb_strtolower($content);

        // Legal area detection patterns
        $patterns = [
            'civil_law' => ['ugovor', 'vlasništvo', 'obitelj', 'nasljeđ', 'odštet'],
            'criminal_law' => ['kazneno', 'prekršaj', 'presuda', 'kazen', 'zatvor'],
            'administrative_law' => ['upravno', 'uprava', 'dozvola', 'inspekcija'],
            'labor_law' => ['rad', 'zaposleni', 'plaća', 'otpremnina', 'radni odnos'],
            'commercial_law' => ['trgovačko', 'društvo', 'stečaj', 'konkurencija'],
            'constitutional_law' => ['ustav', 'pravo', 'sloboda', 'jednakost'],
        ];

        foreach ($patterns as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($content, $keyword) !== false) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        // Extract jurisdiction
        if (isset($metadata['jurisdiction'])) {
            $tags[] = 'jurisdiction_' . strtolower($metadata['jurisdiction']);
        }

        // Extract court level if present
        if (isset($metadata['court'])) {
            $court = mb_strtolower($metadata['court']);
            if (mb_strpos($court, 'vrhovni') !== false) {
                $tags[] = 'supreme_court';
            } elseif (mb_strpos($court, 'županijski') !== false) {
                $tags[] = 'appellate_court';
            }
        }

        return $tags;
    }

    /**
     * Apply a tag to a node
     */
    public function applyTag(string $nodeLabel, string $nodeId, string $tagName): void
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($tagName));

        // Create tag if it doesn't exist
        $this->graph->upsertNode('Tag', $tagId, [
            'name' => $tagName,
            'slug' => str_replace(['_', ' '], '-', strtolower($tagName)),
        ]);

        // Create relationship
        try {
            $this->graph->createRelationship(
                $nodeLabel,
                $nodeId,
                'HAS_TAG',
                'Tag',
                $tagId,
                ['applied_at' => now()->toIso8601String()]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to apply tag', [
                'node_label' => $nodeLabel,
                'node_id' => $nodeId,
                'tag' => $tagName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all tags for a node
     */
    public function getNodeTags(string $nodeLabel, string $nodeId): array
    {
        $query = "MATCH (n:$nodeLabel {id: \$id})-[:HAS_TAG]->(t:Tag)
                  RETURN t.name as name, t.category as category, t.slug as slug
                  ORDER BY t.category, t.name";

        $result = $this->graph->run($query, ['id' => $nodeId]);

        return $result->map(fn($record) => [
            'name' => $record->get('name'),
            'category' => $record->get('category'),
            'slug' => $record->get('slug'),
        ])->toArray();
    }

    /**
     * Get nodes by tag
     */
    public function getNodesByTag(string $tagName, string $nodeLabel = null, int $limit = 50): array
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($tagName));

        $nodePattern = $nodeLabel ? "(n:$nodeLabel)" : "(n)";

        $query = "MATCH $nodePattern-[:HAS_TAG]->(t:Tag {id: \$tagId})
                  RETURN n
                  LIMIT \$limit";

        $result = $this->graph->run($query, [
            'tagId' => $tagId,
            'limit' => $limit,
        ]);

        return $result->map(fn($record) => $record->get('n')->getProperties())->toArray();
    }

    /**
     * Get related tags (tags that often appear together)
     */
    public function getRelatedTags(string $tagName, int $limit = 10): array
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($tagName));

        $query = "MATCH (t:Tag {id: \$tagId})<-[:HAS_TAG]-(n)-[:HAS_TAG]->(related:Tag)
                  WHERE t <> related
                  RETURN related.name as name, related.category as category, count(*) as frequency
                  ORDER BY frequency DESC
                  LIMIT \$limit";

        $result = $this->graph->run($query, [
            'tagId' => $tagId,
            'limit' => $limit,
        ]);

        return $result->map(fn($record) => [
            'name' => $record->get('name'),
            'category' => $record->get('category'),
            'frequency' => $record->get('frequency'),
        ])->toArray();
    }

    /**
     * Get tag hierarchy path
     */
    public function getTagHierarchy(string $tagName): array
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($tagName));

        $query = "MATCH path = (t:Tag {id: \$tagId})-[:PARENT_TAG*0..]->(parent:Tag)
                  RETURN [tag in nodes(path) | tag.name] as hierarchy";

        $result = $this->graph->run($query, ['tagId' => $tagId]);

        if ($result->count() === 0) {
            return [];
        }

        return $result->first()->get('hierarchy');
    }

    /**
     * Remove a tag from a node
     */
    public function removeTag(string $nodeLabel, string $nodeId, string $tagName): void
    {
        $tagId = 'tag_' . str_replace(['-', ' '], '_', strtolower($tagName));

        $query = "MATCH (n:$nodeLabel {id: \$nodeId})-[r:HAS_TAG]->(t:Tag {id: \$tagId})
                  DELETE r";

        $this->graph->run($query, [
            'nodeId' => $nodeId,
            'tagId' => $tagId,
        ]);
    }
}

