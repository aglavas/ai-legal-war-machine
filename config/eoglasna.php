<?php
// config/eoglasna.php

return [
    'base_url' => env('E_OGLASNA_BASE_URL', 'https://e-oglasna.pravosudje.hr'),
    'timeout' => env('E_OGLASNA_TIMEOUT', 15), // seconds
    'connect_timeout' => env('E_OGLASNA_CONNECT_TIMEOUT', 10), // seconds

    // Throttling between requests (proactive)
    'min_delay_ms' => env('E_OGLASNA_MIN_DELAY_MS', 1100), // ~1 req/sec to satisfy "5 per 5 sec"

    // Max hour cap (soft guard; does not guarantee perfect distribution)
    'max_requests_per_hour' => env('E_OGLASNA_MAX_REQUESTS_PER_HOUR', 950), // keep under 1000

    // On 429 the header X-Rate-Limit-Retry-After-Milliseconds is returned; we add some jitter
    'retry_backoff_jitter_ms' => env('E_OGLASNA_RETRY_JITTER_MS', 100),

    // Deep scan hard cap of pages per run for safety (set high, but not infinite)
    'deep_scan_max_pages' => env('E_OGLASNA_DEEP_SCAN_MAX_PAGES', 500),

    // Default sort
    'default_sort' => env('E_OGLASNA_DEFAULT_SORT', 'datePublished,desc'),
];
