<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use App\Services\GraphRagService;
use Illuminate\Console\Command;

class GraphQueryCommand extends Command
{
    protected $signature = 'graph:query
                            {query : Natural language query}
                            {--type=both : Type of content to search (law|case|both)}
                            {--limit=10 : Maximum results to return}';

    protected $description = 'Query the graph database using natural language';

    public function __construct(
        protected GraphRagService $graphRag,
        protected GraphDatabaseService $graph
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('neo4j.sync.enabled')) {
            $this->warn('Neo4j integration is disabled.');
            return self::FAILURE;
        }

        $query = $this->argument('query');
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        $this->info("Querying graph database: '$query'");
        $this->newLine();

        try {
            $results = $this->graphRag->enhancedQuery($query, $type, $limit);

            $this->displayResults($results);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Query failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function displayResults(array $results): void
    {
        if (!empty($results['related_via_keywords'])) {
            $this->info('Related via Keywords:');
            foreach ($results['related_via_keywords'] as $result) {
                $node = $result['node'];
                $this->line(sprintf(
                    '  - %s (weight: %.2f, keyword: %s)',
                    $node['title'] ?? $node['id'],
                    $result['weight'],
                    $result['keyword']
                ));
            }
            $this->newLine();
        }

        if (empty($results['related_via_keywords']) && empty($results['similar_documents'])) {
            $this->warn('No results found.');
        }
    }
}

