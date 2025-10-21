<?php

namespace App\Console\Commands;

use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use App\GraphQL\AutoDiscovery\Introspection\GraphQLIntrospectionService;
use App\GraphQL\AutoDiscovery\SchemaCacheRepository;
use Illuminate\Console\Command;

class GraphQLIntrospectCommand extends Command
{
    protected $signature = 'graphql:introspect {--force : Force refresh even if cache exists}';
    protected $description = 'Fetch and cache the GraphQL schema via introspection';

    public function handle(
        GraphQLIntrospectionService $introspector,
        SchemaCacheRepository $cache
    ): int {
        if (!$this->option('force') && !$cache->isEmpty()) {
            $this->info('Schema already cached. Use --force to refresh.');
            return 0;
        }
        try {
            $this->info('Running introspection...');
            $schema = $introspector->introspect();
            $cache->put($schema);
            $this->info('Schema cached successfully.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Introspection failed: ' . $e->getMessage());
            return 1;
        }
    }
}
