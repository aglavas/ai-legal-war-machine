<?php

return [
    'base_url' => env('ODLUKE_BASE_URL', 'https://odluke.sudovi.hr'),
    'timeout' => env('ODLUKE_TIMEOUT', 30),
    'retry' => env('ODLUKE_RETRY', 2),
    'delay_ms' => env('ODLUKE_DELAY_MS', 700),
    'rpm' => env('ODLUKE_RPM', 30), // max requests per minute (best-effort, per-process)
    'backoff_ms' => env('ODLUKE_BACKOFF_MS', 800), // extra backoff on 429/5xx between retries
];
