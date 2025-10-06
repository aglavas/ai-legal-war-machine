<?php

namespace App\Services\AI\LLM;

class ChatMessage
{
    public function __construct(
        public string $role,   // system|user|assistant
        public string $content
    ) {}
}
