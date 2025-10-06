<?php

return [
    'base_url' => env('ODLUKE_BASE_URL', 'https://odluke.sudovi.hr'),
    'timeout' => env('ODLUKE_TIMEOUT', 30),
    'retry' => env('ODLUKE_RETRY', 2),
    'delay_ms' => env('ODLUKE_DELAY_MS', 700),
];

