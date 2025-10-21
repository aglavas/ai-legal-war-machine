<?php

namespace App\Providers;

use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use App\GraphQL\AutoDiscovery\Introspection\GraphQLIntrospectionService;
use App\GraphQL\AutoDiscovery\QueryBuilder;
use App\GraphQL\AutoDiscovery\SchemaCacheRepository;
use Illuminate\Support\ServiceProvider;

class GraphQLDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // If your config/graphql_client.php exists, Laravel already loads it.
        // If you package-ize this, use mergeConfigFrom() with your package path.

        // Only GraphQLAutoClient needs a singleton; others can be auto-wired now.
        $this->app->singleton(GraphQLAutoClient::class, function ($app) {
            return new GraphQLAutoClient(
                $app->make(GraphQLIntrospectionService::class),
                $app->make(SchemaCacheRepository::class),
                $app->make(QueryBuilder::class),
                $app['config']
            );
        });
    }

    public function boot(): void
    {
        if (config('graphql_client.auto_discover')) {
            $this->app->make(GraphQLAutoClient::class)->warmUp();
        }

        if ($this->app->runningInConsole()) {
            // Publish config if you had a default in a package; otherwise skip
            // $this->publishes([...], 'config');

            $this->commands([
                \App\Console\Commands\GraphQLIntrospectCommand::class,
                \App\Console\Commands\GraphQLListOperationsCommand::class,
                \App\Console\Commands\GraphQLClearSchemaCacheCommand::class,
            ]);
        }
    }
}
