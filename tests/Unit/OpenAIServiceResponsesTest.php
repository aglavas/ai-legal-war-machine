<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OpenAIServiceResponsesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure base config is set for tests
        Config::set('openai.api_key', 'test-key');
        Config::set('openai.base_url', 'https://api.openai.com/v1');
        Config::set('openai.organization', null);
        Config::set('openai.project', null);
    }

    public function test_responses_list_builds_query_and_beta_header()
    {
        $svc = app(OpenAIService::class);

        Http::fake(function ($request) {
            // Assert method and path
            $this->assertEquals('GET', $request->method());
            $this->assertStringStartsWith('https://api.openai.com/v1/responses', (string) $request->url());

            // Parse query
            $url = parse_url((string) $request->url());
            parse_str($url['query'] ?? '', $q);

            // include[] becomes array; depending on parse_str, include will be an array
            $this->assertArrayHasKey('include', $q);
            $this->assertContains('message.input_image.image_url', (array) $q['include']);

            $this->assertEquals('1', $q['input_item_limit'] ?? null);
            $this->assertEquals('1', $q['output_item_limit'] ?? null);

            // Headers
            $this->assertEquals('responses=v1', $request->header('OpenAI-Beta')[0] ?? null);
            $this->assertEquals('Bearer test-key', $request->header('Authorization')[0] ?? null);

            return Http::response(['object' => 'list', 'data' => []], 200);
        });

        $resp = $svc->responsesList([
            'input_item_limit' => 1,
            'output_item_limit' => 1,
        ], ['message.input_image.image_url']);

        $this->assertIsArray($resp);
        $this->assertEquals('list', $resp['object'] ?? null);
        $this->assertEquals([], $resp['data'] ?? null);
    }

    public function test_response_input_items_endpoint_and_header()
    {
        $svc = app(OpenAIService::class);

        Http::fake(function ($request) {
            $this->assertEquals('GET', $request->method());
            $this->assertStringContainsString('/responses/resp_123/input_items', (string) $request->url());
            $this->assertEquals('responses=v1', $request->header('OpenAI-Beta')[0] ?? null);
            return Http::response(['object' => 'list', 'data' => []], 200);
        });

        $resp = $svc->responseInputItems('resp_123', [
            'message.input_image.image_url',
            'computer_call_output.output.image_url',
            'file_search_call.results',
        ]);

        $this->assertEquals('list', $resp['object'] ?? null);
    }
}

