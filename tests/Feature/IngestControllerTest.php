<?php

namespace Tests\Feature;

use App\Models\AgentVectorMemory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IngestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeEmbeddingsFor(array $inputs): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => function ($request) use ($inputs) {
                $payload = $request->data();
                $in = $payload['input'] ?? [];
                // Generate embedding with fixed values per input
                $data = [];
                foreach ((array) $in as $idx => $text) {
                    $data[] = ['embedding' => [0.1, 0.2, 0.3]];
                }
                return Http::response(['data' => $data, 'model' => 'text-embedding-3-small'], 200);
            },
        ]);
    }

    public function test_ingest_text_creates_memories(): void
    {
        $text = str_repeat('Hello world ', 50); // long enough for multiple chunks
        $this->fakeEmbeddingsFor([$text]);

        $res = $this->postJson('/api/ingest/text', [
            'agent' => 'support',
            'namespace' => 'docs',
            'text' => $text,
            'chunk_chars' => 200,
            'overlap' => 20,
        ]);

        $res->assertOk();
        $inserted = $res->json('inserted');
        $this->assertIsInt($inserted);
        $this->assertGreaterThan(0, $inserted);
        $this->assertSame($inserted, AgentVectorMemory::where('agent_name', 'support')->count());
    }

    public function test_ingest_file_creates_memories(): void
    {
        Storage::fake('local');
        $content = str_repeat("Line one. ", 80);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('sample.txt', $content);

        $this->fakeEmbeddingsFor([$content]);

        $res = $this->postJson('/api/ingest/file', [
            'agent' => 'support',
            'namespace' => 'files',
            'file' => $file,
            'chunk_chars' => 300,
            'overlap' => 30,
        ]);

        $res->assertOk();
        $this->assertGreaterThan(0, $res->json('inserted'));
        $this->assertSame($res->json('inserted'), AgentVectorMemory::where('namespace', 'files')->count());
    }

    public function test_ingest_text_is_idempotent_by_hash(): void
    {
        $text = 'Repeat me once.';
        $this->fakeEmbeddingsFor([$text]);

        $res1 = $this->postJson('/api/ingest/text', [
            'agent' => 'bot',
            'text' => $text,
            'chunk_chars' => 200,
            'overlap' => 0,
        ])->assertOk();
        $this->assertSame(1, $res1->json('inserted'));

        // Second ingestion of same content should insert 0
        $this->fakeEmbeddingsFor([$text]);
        $res2 = $this->postJson('/api/ingest/text', [
            'agent' => 'bot',
            'text' => $text,
        ])->assertOk();
        $this->assertSame(0, $res2->json('inserted'));

        $this->assertSame(1, AgentVectorMemory::where('agent_name', 'bot')->count());
    }
}

