<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORG'),
    'project' => env('OPENAI_PROJECT'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    // Default model hints for convenience
    'models' => [
        'responses' => env('OPENAI_RESPONSES_MODEL', 'gpt-4.1-mini'),
        'chat' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embeddings' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'image' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'stt' => env('OPENAI_STT_MODEL', 'whisper-1'),
        'tts' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
    ],

    // HTTP options
    'timeout' => env('OPENAI_TIMEOUT', 60),
    'connect_timeout' => env('OPENAI_CONNECT_TIMEOUT', 10),
    'retry' => [
        'times' => env('OPENAI_RETRY_TIMES', 2),
        'sleep_ms' => env('OPENAI_RETRY_SLEEP_MS', 200),
    ],
];

