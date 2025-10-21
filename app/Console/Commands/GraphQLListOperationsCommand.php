<?php

namespace App\Console\Commands;

use App\GraphQL\AutoDiscovery\SchemaCacheRepository;
use Illuminate\Console\Command;

class GraphQLListOperationsCommand extends Command
{
    protected $signature = 'graphql:operations';
    protected $description = 'List discovered Query root operations and their args';

    public function handle(SchemaCacheRepository $cache): int
    {
        $payload = $cache->get();
        if (!$payload) {
            $this->error('No schema cached. Run: php artisan graphql:introspect');
            return 1;
        }
        $schema = $payload['schema']['__schema'] ?? null;
        if (!$schema) {
            $this->error('Invalid schema in cache.');
            return 1;
        }
        $queryTypeName = $schema['queryType']['name'] ?? 'Query';
        $queryType = null;
        foreach ($schema['types'] as $t) {
            if (($t['name'] ?? '') === $queryTypeName) {
                $queryType = $t;
                break;
            }
        }
        if (!$queryType) {
            $this->error('Query root type not found in schema.');
            return 1;
        }

        foreach ($queryType['fields'] ?? [] as $f) {
            $args = array_map(function ($a) {
                $n = $a['name'];
                $k = $a['type']['kind'];
                $name = $a['type']['name'] ?? null;
                // A short type string (not full recursion to keep it simple in CLI)
                return "$n: " . ($name ?? $k);
            }, $f['args'] ?? []);
            $this->line(sprintf("- %s(%s)", $f['name'], implode(', ', $args)));
        }

        return 0;
    }
}
