<?php

namespace App\Providers;

use App\Services\GraphDatabaseService;
use App\Services\GraphRagService;
use App\Services\TaggingService;
use Illuminate\Support\ServiceProvider;

class GraphServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GraphDatabaseService::class, function ($app) {
            return new GraphDatabaseService();
        });

        $this->app->singleton(TaggingService::class, function ($app) {
            return new TaggingService(
                $app->make(GraphDatabaseService::class)
            );
        });

        $this->app->singleton(GraphRagService::class, function ($app) {
            return new GraphRagService(
                $app->make(GraphDatabaseService::class),
                $app->make(TaggingService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\GraphInitCommand::class,
                \App\Console\Commands\GraphSyncCommand::class,
                \App\Console\Commands\GraphQueryCommand::class,
                \App\Console\Commands\GraphStatsCommand::class,
            ]);
        }
    }
}

