<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenAIControllerTest extends TestCase
{
    public function test_responses_endpoint(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'id' => 'resp_123',
                'output' => [['content' => [['type' => 'output_text', 'text' => 'Hello']]]]], 200),
        ]);

        $res = $this->postJson('/api/openai/responses', [
            'input' => 'Say hello',
        ]);
        $res->assertStatus(200)
            ->assertJsonFragment(['id' => 'resp_123']);
    }

    public function test_embeddings_endpoint(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [['embedding' => [0.1, 0.2]]],
                'model' => 'text-embedding-3-small',
            ], 200),
        ]);

        $res = $this->postJson('/api/openai/embeddings', [
            'input' => 'test',
        ]);
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function test_tts_endpoint(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response('AUDIO', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $res = $this->postJson('/api/openai/tts', [
            'text' => 'hi',
            'format' => 'mp3',
        ]);
        $res->assertOk();
        $this->assertEquals('audio/mpeg', $res->headers->get('Content-Type'));
    }

    public function test_files_upload_proxy(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.openai.com/*' => Http::response(['id' => 'file_abc', 'status' => 'uploaded'], 200),
        ]);

        $res = $this->postJson('/api/openai/files', [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('doc.txt', 1, 'text/plain'),
            'purpose' => 'assistants',
        ]);
        $res->assertOk()->assertJsonFragment(['id' => 'file_abc']);
    }
}

