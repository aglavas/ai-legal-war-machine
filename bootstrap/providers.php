<?php

return [
    App\Providers\AppServiceProvider::class,
    // Register MCP Odluke tools provider to ensure tools are available in all environments
    App\Providers\McpOdlukeServiceProvider::class,
    App\Providers\GraphServiceProvider::class,
    App\Providers\EkomServiceProvider::class,
    App\Providers\Neo4jServiceProvider::class,
    App\Providers\GraphQLDiscoveryServiceProvider::class,

];
