<?php

namespace App\Console\Commands;

use App\Services\GraphRagService;
use Closure;
use Illuminate\Console\Command;

class GraphSyncCommand extends Command
{
    protected $signature = 'graph:sync
                            {--all : Sync all data}
                            {--laws : Sync laws only}
                            {--cases : Sync cases only}
                            {--limit= : Limit number of records to sync}';

    protected $description = 'Sync data from relational database to Neo4j graph database';

    public function __construct(protected GraphRagService $graphRag)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('neo4j.sync.enabled')) {
            $this->warn('Neo4j integration is disabled. Enable it in config/neo4j.php');
            return self::FAILURE;
        }

        $syncAll = $this->option('all');
        $syncLaws = $this->option('laws');
        $syncCases = $this->option('cases');

        if (!$syncAll && !$syncLaws && !$syncCases) {
            $this->error('Please specify what to sync: --all, --laws, or --cases');
            return self::FAILURE;
        }

        try {
            if ($syncAll || $syncLaws) {
                $this->info('Syncing laws to graph database...');
                $this->withProgressBar(1, function () {
                    return $this->graphRag->syncAllLaws();
                });
            }

            if ($syncAll || $syncCases) {
                $this->info('Syncing cases to graph database...');
                $this->withProgressBar(1, function () {
                    return $this->graphRag->syncAllCases();
                });
            }

            $this->newLine(2);
            $this->info('Sync completed successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    public function withProgressBar($totalSteps, Closure $callback): void
    {
        $bar = $this->output->createProgressBar();
        $bar->start();

        $result = $callback();

        $bar->finish();
        $this->newLine();

        if (isset($result['synced'])) {
            $this->info("✓ Synced: {$result['synced']}");
        }
        if (isset($result['errors']) && $result['errors'] > 0) {
            $this->warn("⚠ Errors: {$result['errors']}");
        }
    }
}

