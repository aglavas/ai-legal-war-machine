<?php

return [
    App\Providers\AppServiceProvider::class,
    // Register MCP Odluke tools provider to ensure tools are available in all environments
    App\Providers\McpOdlukeServiceProvider::class,
    // Register Internal MCP client for direct tool access (dashboard, artisan)
    App\Providers\InternalMcpServiceProvider::class,
    App\Providers\GraphServiceProvider::class,
    App\Providers\EkomServiceProvider::class,
    App\Providers\Neo4jServiceProvider::class,
    App\Providers\GraphQLDiscoveryServiceProvider::class,

];
