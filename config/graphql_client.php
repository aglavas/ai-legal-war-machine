<?php

return [
    'endpoint' => env('GRAPHQL_ENDPOINT', ''),

    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    'auth' => [
        'header' => env('GRAPHQL_AUTH_HEADER', 'Authorization'),
        'bearer' => env('GRAPHQL_TOKEN', null), // if set, will be used as "Authorization: Bearer <token>"
    ],

    'timeout' => 15,
    'connect_timeout' => 5,

    'cache' => [
        'store' => env('GRAPHQL_CACHE_STORE', 'file'),
        'ttl' => 60 * 60 * 24, // 24h
    ],

    'auto_discover' => env('GRAPHQL_AUTO_DISCOVER', true),

    // If introspection is disabled or fails, we can fall back to hints
    'fallback' => [
        'enabled' => true,
        'operations_hints' => base_path('bootstrap/graphql.operations.php'),
    ],

    // Auto selection defaults
    'selection' => [
        'default_depth' => 1,   // include 1 nested level
        'max_fields' => 50,     // guardrail for auto-selected field count
    ],
];
