<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;

class GraphDatabaseService
{
    protected ClientInterface $client;
    protected SessionConfiguration $sessionConfig;
    protected string $database;

    public function __construct()
    {
        $this->initializeClient();
        $this->database = config('neo4j.connections.bolt.database', 'neo4j');
    }

    protected function initializeClient(): void
    {
        $config = config('neo4j.connections.' . config('neo4j.default', 'bolt'));

        $auth = Authenticate::basic(
            $config['username'] ?? $config['user'] ?? 'neo4j',
            $config['password'] ?? ''
        );

        // Accept scheme from 'scheme' or fallback to 'driver'
        $scheme = $config['scheme'] ?? $config['driver'] ?? 'bolt';
        if (!empty($config['tls']) && strpos($scheme, '+s') === false) {
            $scheme .= '+s';
        }

        $uri = sprintf(
            '%s://%s:%s',
            $scheme,
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 7687
        );

        // Use a predictable alias name that is NOT the database name
        $this->client = ClientBuilder::create()
            ->withDriver('default', $uri, $auth)
            ->withDefaultDriver('default')
            ->build();

        $this->database = $config['database'] ?? 'neo4j';
        $this->sessionConfig = SessionConfiguration::default()->withDatabase($this->database);
    }

    /**
     * Execute a Cypher query
     */
    public function run(string $query, array $parameters = []): mixed
    {
        try {
            return $this->client->run(
                $query,
                $parameters,
                null,                   // alias (keep default)
                $this->sessionConfig    // database here
            );
        } catch (\Exception $e) {
            Log::error('Neo4j query failed', [
                'query' => $query,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Run multiple queries in a transaction
     */
    public function transaction(callable $callback): mixed
    {
        return $this->client->writeTransaction(
            function ($tsx) use ($callback) {
                return $callback($tsx);
            },
            null,                   // alias (keep default)
            $this->sessionConfig    // database here
        );
    }

    /**
     * Initialize graph schema with indexes and constraints
     */
    public function initializeSchema(): void
    {
        $this->createConstraints();
        $this->createIndexes();
    }

    protected function createConstraints(): void
    {
        $constraints = [
            // Law nodes
            "CREATE CONSTRAINT law_id IF NOT EXISTS FOR (l:Law) REQUIRE l.id IS UNIQUE",
            "CREATE CONSTRAINT law_doc_id IF NOT EXISTS FOR (ld:LawDocument) REQUIRE ld.id IS UNIQUE",

            // Case nodes
            "CREATE CONSTRAINT case_id IF NOT EXISTS FOR (c:Case) REQUIRE c.id IS UNIQUE",
            "CREATE CONSTRAINT case_doc_id IF NOT EXISTS FOR (cd:CaseDocument) REQUIRE cd.id IS UNIQUE",

            // Keyword and Tag nodes
            "CREATE CONSTRAINT keyword_name IF NOT EXISTS FOR (k:Keyword) REQUIRE k.name IS UNIQUE",
            "CREATE CONSTRAINT tag_name IF NOT EXISTS FOR (t:Tag) REQUIRE t.name IS UNIQUE",

            // Jurisdiction and Court nodes
            "CREATE CONSTRAINT jurisdiction_name IF NOT EXISTS FOR (j:Jurisdiction) REQUIRE j.name IS UNIQUE",
            "CREATE CONSTRAINT court_name IF NOT EXISTS FOR (c:Court) REQUIRE c.name IS UNIQUE",

            // Topic and Concept nodes
            "CREATE CONSTRAINT topic_name IF NOT EXISTS FOR (t:Topic) REQUIRE t.name IS UNIQUE",
            "CREATE CONSTRAINT concept_name IF NOT EXISTS FOR (lc:LegalConcept) REQUIRE lc.name IS UNIQUE",
        ];

        foreach ($constraints as $constraint) {
            try {
                $this->run($constraint);
            } catch (\Exception $e) {
                Log::warning('Failed to create constraint', [
                    'constraint' => $constraint,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function createIndexes(): void
    {
        $indexes = [
            // Text indexes for search
            "CREATE INDEX law_title IF NOT EXISTS FOR (l:Law) ON (l.title)",
            "CREATE INDEX law_number IF NOT EXISTS FOR (l:Law) ON (l.law_number)",
            "CREATE INDEX case_title IF NOT EXISTS FOR (c:Case) ON (c.title)",

            // Date indexes
            "CREATE INDEX law_effective_date IF NOT EXISTS FOR (l:Law) ON (l.effective_date)",
            "CREATE INDEX case_decision_date IF NOT EXISTS FOR (c:Case) ON (c.decision_date)",

            // Metadata indexes
            "CREATE INDEX keyword_category IF NOT EXISTS FOR (k:Keyword) ON (k.category)",
            "CREATE INDEX tag_category IF NOT EXISTS FOR (t:Tag) ON (t.category)",
        ];

        foreach ($indexes as $index) {
            try {
                $this->run($index);
            } catch (\Exception $e) {
                Log::warning('Failed to create index', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create or update a node
     */
    public function upsertNode(string $label, string $id, array $properties): void
    {
        $properties['id'] = $id;
        $properties['updated_at'] = now()->toIso8601String();

        if (!isset($properties['created_at'])) {
            $properties['created_at'] = now()->toIso8601String();
        }

        $query = "MERGE (n:$label {id: \$id})
                  SET n += \$properties
                  RETURN n";

        $this->run($query, [
            'id' => $id,
            'properties' => $properties,
        ]);
    }

    /**
     * Create a relationship between two nodes
     */
    public function createRelationship(
        string $fromLabel,
        string $fromId,
        string $relType,
        string $toLabel,
        string $toId,
        array $properties = []
    ): void {
        $properties['created_at'] = now()->toIso8601String();

        $query = "MATCH (from:$fromLabel {id: \$fromId})
                  MATCH (to:$toLabel {id: \$toId})
                  MERGE (from)-[r:$relType]->(to)
                  SET r += \$properties
                  RETURN r";

        $this->run($query, [
            'fromId' => $fromId,
            'toId' => $toId,
            'properties' => $properties,
        ]);
    }

    /**
     * Delete a node and all its relationships
     */
    public function deleteNode(string $label, string $id): void
    {
        $query = "MATCH (n:$label {id: \$id})
                  DETACH DELETE n";

        $this->run($query, ['id' => $id]);
    }

    /**
     * Find similar nodes based on properties
     */
    public function findSimilar(string $label, array $properties, int $limit = 10): array
    {
        $conditions = [];
        $params = [];

        foreach ($properties as $key => $value) {
            $conditions[] = "n.$key = \$$key";
            $params[$key] = $value;
        }

        $where = implode(' OR ', $conditions);
        $params['limit'] = $limit;

        $query = "MATCH (n:$label)
                  WHERE $where
                  RETURN n
                  LIMIT \$limit";

        $result = $this->run($query, $params);

        return $result->map(fn($record) => $record->get('n')->getProperties())->toArray();
    }

    /**
     * Get related nodes
     */
    public function getRelated(
        string $label,
        string $id,
        string $relType = null,
        int $depth = 1,
        int $limit = 50
    ): array {
        $relPattern = $relType ? "-[:$relType*1..$depth]-" : "-[*1..$depth]-";

        $query = "MATCH (n:$label {id: \$id})$relPattern(related)
                  RETURN DISTINCT related
                  LIMIT \$limit";

        $result = $this->run($query, [
            'id' => $id,
            'limit' => $limit,
        ]);

        return $result->map(fn($record) => $record->get('related')->getProperties())->toArray();
    }

    /**
     * Get shortest path between two nodes
     */
    public function findPath(
        string $fromLabel,
        string $fromId,
        string $toLabel,
        string $toId,
        int $maxDepth = 5
    ): ?array {
        $query = "MATCH path = shortestPath(
                    (from:$fromLabel {id: \$fromId})-[*1..$maxDepth]-(to:$toLabel {id: \$toId})
                  )
                  RETURN path";

        $result = $this->run($query, [
            'fromId' => $fromId,
            'toId' => $toId,
        ]);

        if ($result->count() === 0) {
            return null;
        }

        return $result->first()->get('path');
    }

    /**
     * Get node with relationships
     */
    public function getNodeWithRelationships(string $label, string $id): ?array
    {
        $query = "MATCH (n:$label {id: \$id})
                  OPTIONAL MATCH (n)-[r]->(related)
                  RETURN n, collect({type: type(r), node: related, properties: properties(r)}) as relationships";

        $result = $this->run($query, ['id' => $id]);

        if ($result->count() === 0) {
            return null;
        }

        $record = $result->first();
        return [
            'node' => $record->get('n')->getProperties(),
            'relationships' => $record->get('relationships'),
        ];
    }

    /**
     * Batch upsert nodes
     */
    public function batchUpsertNodes(string $label, array $nodes): void
    {
        $query = "UNWIND \$nodes AS node
                  MERGE (n:$label {id: node.id})
                  SET n += node
                  RETURN count(n) as count";

        $this->run($query, ['nodes' => $nodes]);
    }

    /**
     * Clear all data (use with caution!)
     */
    public function clearAll(): void
    {
        $this->run("MATCH (n) DETACH DELETE n");
    }
}
