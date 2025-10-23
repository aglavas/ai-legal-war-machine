<?php

namespace App\Providers;

use App\Services\Mcp\InternalMcpClient;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for Internal MCP Tools
 *
 * Registers MCP tools for internal use (dashboard, artisan commands)
 * without requiring HTTP transport.
 */
class InternalMcpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(InternalMcpClient::class, function ($app) {
            return new InternalMcpClient();
        });

        // Register as 'mcp.internal' for easy access
        $this->app->alias(InternalMcpClient::class, 'mcp.internal');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
