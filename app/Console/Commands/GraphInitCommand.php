<?php

namespace App\Console\Commands;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Console\Command;

class GraphInitCommand extends Command
{
    protected $signature = 'graph:init
                            {--force : Force reinitialization of schema}';

    protected $description = 'Initialize Neo4j graph database schema and constraints';

    public function __construct(
        protected GraphDatabaseService $graph,
        protected TaggingService $tagging
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('neo4j.sync.enabled')) {
            $this->warn('Neo4j integration is disabled. Enable it in config/neo4j.php');
            return self::FAILURE;
        }

        $this->info('Initializing Neo4j graph database...');

        try {
            // Initialize schema
            $this->info('Creating constraints and indexes...');
            $this->graph->initializeSchema();
            $this->info('✓ Schema initialized');

            // Initialize tag hierarchy
            $this->info('Creating tag hierarchy...');
            $this->tagging->initializeTagHierarchy();
            $this->info('✓ Tag hierarchy created');

            $this->info('');
            $this->info('Graph database initialized successfully!');
            $this->info('');
            $this->info('Next steps:');
            $this->info('1. Run: php artisan graph:sync --all');
            $this->info('2. Or sync specific types: php artisan graph:sync --laws');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to initialize graph database: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return self::FAILURE;
        }
    }
}

