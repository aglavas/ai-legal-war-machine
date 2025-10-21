<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Blade;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure app key exists for encryption/cookies in HTTP tests
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        // Provide a dummy OpenAI API key so the service doesn't throw
        config(['openai.api_key' => 'test-openai-key']);
        // Make @vite a no-op in tests to prevent missing manifest/dev-server issues
        Blade::directive('vite', fn($expression) => '');
    }
}
