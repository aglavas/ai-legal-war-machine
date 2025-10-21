<?php

namespace App\Console\Commands;

use App\GraphQL\AutoDiscovery\SchemaCacheRepository;
use Illuminate\Console\Command;

class GraphQLClearSchemaCacheCommand extends Command
{
    protected $signature = 'graphql:clear-schema-cache';
    protected $description = 'Clear cached GraphQL schema';

    public function handle(SchemaCacheRepository $cache): int
    {
        $cache->clear();
        $this->info('GraphQL schema cache cleared.');
        return 0;
    }
}
