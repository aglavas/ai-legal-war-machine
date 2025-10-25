<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Autonomous Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for autonomous research agents including default limits,
    | evaluation settings, and scheduling options.
    |
    */

    'defaults' => [
        // Maximum iterations per research run
        'max_iterations' => env('AGENT_MAX_ITERATIONS', 10),

        // Time limit in seconds (default: 10 minutes)
        'time_limit_seconds' => env('AGENT_TIME_LIMIT', 600),

        // Quality threshold for evaluation (0-1)
        'threshold' => env('AGENT_THRESHOLD', 0.75),

        // Token budget (null = unlimited)
        'token_budget' => env('AGENT_TOKEN_BUDGET', null),

        // Cost budget in USD (null = unlimited)
        'cost_budget' => env('AGENT_COST_BUDGET', null),
    ],

    'evaluation' => [
        // Weights for different evaluation criteria (must sum to 1.0)
        'weights' => [
            'completeness' => 0.25,
            'citations' => 0.25,
            'relevance' => 0.20,
            'quality' => 0.15,
            'evidence' => 0.15,
        ],

        // Minimum score for each criterion to be considered "passed"
        'pass_thresholds' => [
            'completeness' => 0.7,
            'citations' => 0.6,
            'relevance' => 0.7,
            'quality' => 0.7,
            'evidence' => 0.6,
        ],

        // Citation requirements
        'citations' => [
            // Minimum number of citations for full credit
            'min_count' => 5,

            // Good citation count (80% score)
            'good_count' => 3,

            // Acceptable citation count (60% score)
            'acceptable_count' => 1,
        ],

        // Quality requirements
        'quality' => [
            // Minimum word count for full credit
            'min_words' => 200,

            // Good word count
            'good_words' => 100,

            // Minimum acceptable word count
            'min_acceptable_words' => 50,
        ],

        // Evidence requirements
        'evidence' => [
            // Minimum successful actions for full credit
            'min_actions' => 10,

            // Good action count
            'good_actions' => 5,

            // Minimum acceptable actions
            'min_acceptable_actions' => 3,
        ],
    ],

    'toolbox' => [
        // Vector search settings
        'vector_search' => [
            'default_limit' => 10,
            'max_limit' => 50,
            'min_similarity' => 0.7,
        ],

        // Web fetch settings
        'web_fetch' => [
            'timeout' => 30,
            'max_redirects' => 5,
            'user_agent' => 'AI-Legal-War-Machine/1.0',
        ],

        // Graph query settings
        'graph_query' => [
            'timeout' => 60,
            'max_results' => 100,
        ],
    ],

    'scheduling' => [
        // When to run scheduled research
        'cron' => env('AGENT_SCHEDULE_CRON', 'weekly'),

        // Day of week (0-6, 0 = Sunday)
        'day' => env('AGENT_SCHEDULE_DAY', 0),

        // Time of day (24-hour format)
        'time' => env('AGENT_SCHEDULE_TIME', '02:00'),

        // Number of iterations for scheduled runs
        'scheduled_max_iterations' => 15,

        // Time limit for scheduled runs (30 minutes)
        'scheduled_time_limit' => 1800,

        // Minimum number of past successful runs to determine topics
        'min_successful_runs' => 3,

        // Maximum number of topics to research
        'max_topics' => 3,
    ],

    'performance' => [
        // Enable async execution via jobs
        'async_execution' => env('AGENT_ASYNC_EXECUTION', false),

        // Retry settings for failed operations
        'retry' => [
            'max_attempts' => 3,
            'delay_seconds' => 2,
            'exponential_backoff' => true,
        ],

        // Caching settings
        'cache' => [
            // Cache vector search results
            'enable_cache' => env('AGENT_CACHE_ENABLED', true),

            // Cache TTL in seconds (1 hour)
            'ttl' => env('AGENT_CACHE_TTL', 3600),
        ],
    ],

    'queue' => [
        // Queue name for agent jobs
        'name' => env('AGENT_QUEUE', 'agents'),

        // Connection to use
        'connection' => env('AGENT_QUEUE_CONNECTION', null),
    ],

    'logging' => [
        // Log level for agent operations
        'level' => env('AGENT_LOG_LEVEL', 'info'),

        // Log channel
        'channel' => env('AGENT_LOG_CHANNEL', 'stack'),

        // Enable detailed iteration logging
        'log_iterations' => env('AGENT_LOG_ITERATIONS', true),

        // Enable action result logging
        'log_actions' => env('AGENT_LOG_ACTIONS', true),
    ],

    'safety' => [
        // Maximum allowed cost per run (safety limit)
        'max_cost_per_run' => env('AGENT_MAX_COST', 5.00),

        // Maximum allowed time per run (safety limit, 1 hour)
        'max_time_per_run' => env('AGENT_MAX_TIME', 3600),

        // Maximum concurrent runs
        'max_concurrent_runs' => env('AGENT_MAX_CONCURRENT', 5),

        // Require approval for runs exceeding limits
        'require_approval' => env('AGENT_REQUIRE_APPROVAL', false),
    ],
];
