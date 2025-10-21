<?php

return [
    'base_url' => env('EKOM_BASE_URL', 'https://e-komunikacija.pravosudje.hr'),
    'token' => env('EKOM_TOKEN', ''),
    'timeout' => (int) env('EKOM_TIMEOUT', 30),
    'retries' => (int) env('EKOM_RETRIES', 2),
    'retry_delay_ms' => (int) env('EKOM_RETRY_DELAY_MS', 300),
    'user_agent' => env('EKOM_USER_AGENT', 'Laravel-Ekom-Client/1.0'),
    // Default page size for sync commands (API max = 100)
    'default_page_size' => (int) env('EKOM_DEFAULT_PAGE_SIZE', 50),
];
