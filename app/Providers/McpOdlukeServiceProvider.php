<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpMcp\Server\Server as McpServer;

class McpOdlukeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Ensure php-mcp server is resolved and our tools are registered
        try {
            /** @var McpServer $server */
            $server = $this->app->make(McpServer::class);

            // Register Odluke tools explicitly; Schema is inferred from method signatures
            $server
                ->withTool([\App\Mcp\OdlukeTools::class, 'search'], 'odluke-search', 'Search Odluke and return decision IDs')
                ->withTool([\App\Mcp\OdlukeTools::class, 'meta'], 'odluke-meta', 'Fetch metadata for one or more decision IDs')
                ->withTool([\App\Mcp\OdlukeTools::class, 'download'], 'odluke-download', 'Download decision PDF/HTML and optionally save locally')
                ->withTool([\App\Mcp\OdlukeTools::class, 'searchLawArticles'], 'law-articles-search', 'Search ingested laws and law articles by query, law number, or title')
                ->withTool([\App\Mcp\OdlukeTools::class, 'getLawArticleById'], 'law-article-by-id', 'Get a single law article by its ID');
        } catch (\Throwable $e) {
            // Fail-safe: log but don\'t crash app boot
            logger()->warning('McpOdlukeServiceProvider failed to register tools: '.$e->getMessage());
        }
    }
}

