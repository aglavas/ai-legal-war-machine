<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    public function test_direct_upload_stores_file_on_public_disk(): void
    {
        Storage::fake('public');

        $res = $this->postJson('/api/uploads', [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('hello.txt', 'hello world'),
        ]);

        $res->assertOk()->assertJsonStructure(['path', 'url', 'size', 'mime', 'name']);
        $path = $res->json('path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_chunked_upload_flow_complete(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $start = $this->postJson('/api/uploads/start', [
            'filename' => 'big.txt',
            'totalSize' => 6,
            'chunkSize' => 3,
        ])->assertOk();
        $id = $start->json('id');

        $this->postJson("/api/uploads/{$id}/chunk/0", [
            'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part0', 'foo'),
        ])->assertOk();

        $this->postJson("/api/uploads/{$id}/chunk/1", [
            'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part1', 'bar'),
        ])->assertOk();

        $done = $this->postJson("/api/uploads/{$id}/complete")->assertOk();
        $done->assertJsonFragment(['status' => 'completed']);

        $path = $done->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertSame('foobar', Storage::disk('public')->get($path));
    }

    public function test_chunked_upload_incomplete_when_missing_parts(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $start = $this->postJson('/api/uploads/start', [
            'filename' => 'big.txt',
            'totalSize' => 6,
            'chunkSize' => 3,
        ])->assertOk();
        $id = $start->json('id');

        $this->postJson("/api/uploads/{$id}/chunk/0", [
            'chunk' => \Illuminate\Http\UploadedFile::fake()->createWithContent('part0', 'foo'),
        ])->assertOk();

        $done = $this->postJson("/api/uploads/{$id}/complete")->assertOk();
        $done->assertJsonFragment(['status' => 'incomplete']);
        $this->assertSame(2, $done->json('expected'));
    }
}

