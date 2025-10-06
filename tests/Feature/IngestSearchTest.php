<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeEmbeddingsForSearch(string $q, array $chunks): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => function ($request) use ($q, $chunks) {
                $payload = $request->data();
                $input = $payload['input'] ?? [];
                // Return simple 3-dim embeddings; use same vector for all for determinism
                $toVec = function ($text) {
                    $hash = crc32((string)$text);
                    return [ ($hash % 10)/10, (($hash>>8)%10)/10, (($hash>>16)%10)/10 ];
                };
                $data = [];
                if (is_array($input)) {
                    foreach ($input as $text) $data[] = ['embedding' => $toVec($text)];
                } else {
                    $data[] = ['embedding' => $toVec($input)];
                }
                return Http::response(['data' => $data, 'model' => 'text-embedding-3-small'], 200);
            },
        ]);
    }

    public function test_search_returns_results_after_ingest(): void
    {
        $text = str_repeat('Hello Zagreb ', 30);
        $this->fakeEmbeddingsForSearch('Zagreb', [$text]);

        // ingest
        $this->postJson('/api/ingest/text', [
            'agent' => 'law',
            'namespace' => 'nn',
            'text' => $text,
            'chunk_chars' => 200,
            'overlap' => 10,
        ])->assertOk();

        // search
        $res = $this->postJson('/api/ingest/search', [
            'agent' => 'law',
            'namespace' => 'nn',
            'query' => 'Zagreb',
            'limit' => 3,
        ])->assertOk();

        $this->assertGreaterThan(0, $res->json('count'));
        $this->assertNotEmpty($res->json('results'));
        $this->assertArrayHasKey('content', $res->json('results')[0]);
        $this->assertArrayHasKey('score', $res->json('results')[0]);
    }
}

