<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Console\Command;

class GraphStatsCommand extends Command
{
    protected $signature = 'graph:stats';

    protected $description = 'Display statistics about the graph database';

    public function __construct(
        protected GraphDatabaseService $graph,
        protected TaggingService $tagging
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('neo4j.sync.enabled')) {
            $this->warn('Neo4j integration is disabled.');
            return self::FAILURE;
        }

        try {
            $this->info('Graph Database Statistics');
            $this->newLine();

            // Node counts
            $this->info('Node Counts:');
            $nodeCounts = $this->getNodeCounts();
            foreach ($nodeCounts as $label => $count) {
                $this->line("  $label: $count");
            }
            $this->newLine();

            // Relationship counts
            $this->info('Relationship Counts:');
            $relCounts = $this->getRelationshipCounts();
            foreach ($relCounts as $type => $count) {
                $this->line("  $type: $count");
            }
            $this->newLine();

            // Tag statistics
            $this->info('Top Tags:');
            $topTags = $this->getTopTags(10);
            foreach ($topTags as $tag) {
                $this->line(sprintf(
                    "  %s: %d documents",
                    $tag['name'],
                    $tag['count']
                ));
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to retrieve statistics: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function getNodeCounts(): array
    {
        $labels = ['LawDocument', 'CaseDocument', 'Keyword', 'Tag', 'Jurisdiction', 'Court'];
        $counts = [];

        foreach ($labels as $label) {
            $result = $this->graph->run("MATCH (n:$label) RETURN count(n) as count");
            $counts[$label] = $result->first()?->get('count') ?? 0;
        }

        return $counts;
    }

    protected function getRelationshipCounts(): array
    {
        $result = $this->graph->run(
            "MATCH ()-[r]->()
             RETURN type(r) as type, count(r) as count
             ORDER BY count DESC"
        );

        $counts = [];
        foreach ($result as $record) {
            $counts[$record->get('type')] = $record->get('count');
        }

        return $counts;
    }

    protected function getTopTags(int $limit): array
    {
        $result = $this->graph->run(
            "MATCH (t:Tag)<-[:HAS_TAG]-()
             RETURN t.name as name, count(*) as count
             ORDER BY count DESC
             LIMIT \$limit",
            ['limit' => $limit]
        );

        return $result->map(fn($r) => [
            'name' => $r->get('name'),
            'count' => $r->get('count'),
        ])->toArray();
    }
}
