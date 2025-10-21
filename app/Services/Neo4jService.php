<?php

namespace App\Services;

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Authentication\Authenticate;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\Log;

class Neo4jService
{
    protected $client;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = Log::channel('stack');

        $enabled = (bool) config('neo4j.sync.enabled', true);
        if (!$enabled) {
            $this->client = null;
            return;
        }

        $uri = (string) config('neo4j.uri', 'bolt://localhost:7687');
        $user = (string) config('neo4j.user', 'neo4j');
        $password = (string) config('neo4j.password', 'secret');

        $this->client = ClientBuilder::create()
            ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
            ->withDefaultDriver('bolt')
            ->build();
    }

    public function upsertCaseAndDocument(string $caseId, string $caseTitle, string $docId, string $docTitle): void
    {
        if (!$this->client) {
            $this->logger->info('Neo4j disabled; skipping upsert', compact('caseId', 'docId'));
            return;
        }

        $cypher = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title ' .
                  'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title ' .
                  'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        $params = [
            'case_id' => $caseId,
            'case_title' => $caseTitle,
            'doc_id' => $docId,
            'doc_title' => $docTitle,
        ];

        $this->client->run($cypher, $params);
    }
}
