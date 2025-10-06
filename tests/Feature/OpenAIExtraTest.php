<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIExtraTest extends TestCase
{
    public function test_assistants_crud_endpoints(): void
    {
        Http::fake([
            'https://api.openai.com/v1/assistants*' => function ($request) {
                $url = (string) $request->url();
                if (preg_match('#/assistants$#', $url) && $request->method() === 'POST') {
                    return Http::response(['id' => 'asst_1', 'model' => 'gpt-4o-mini'], 200);
                }
                if (preg_match('#/assistants$#', $url) && $request->method() === 'GET') {
                    return Http::response(['data' => [['id' => 'asst_1']]], 200);
                }
                if (preg_match('#/assistants/asst_1$#', $url) && $request->method() === 'GET') {
                    return Http::response(['id' => 'asst_1'], 200);
                }
                if (preg_match('#/assistants/asst_1$#', $url) && $request->method() === 'DELETE') {
                    return Http::response(['id' => 'asst_1', 'deleted' => true], 200);
                }
                return Http::response([], 404);
            },
        ]);

        $create = $this->postJson('/api/openai/assistants', ['name' => 'Helper']);
        $create->assertOk()->assertJsonFragment(['id' => 'asst_1']);

        $list = $this->getJson('/api/openai/assistants');
        $list->assertOk()->assertJsonStructure(['data']);

        $get = $this->getJson('/api/openai/assistants/asst_1');
        $get->assertOk()->assertJsonFragment(['id' => 'asst_1']);

        $del = $this->deleteJson('/api/openai/assistants/asst_1');
        $del->assertOk()->assertJsonFragment(['deleted' => true]);
    }

    public function test_vector_store_endpoints(): void
    {
        Http::fake([
            'https://api.openai.com/v1/vector_stores*' => function ($request) {
                $url = (string) $request->url();
                if (preg_match('#/vector_stores$#', $url) && $request->method() === 'POST') {
                    return Http::response(['id' => 'vs_1', 'name' => 'Store'], 200);
                }
                if (preg_match('#/vector_stores$#', $url) && $request->method() === 'GET') {
                    return Http::response(['data' => [['id' => 'vs_1']]], 200);
                }
                if (preg_match('#/vector_stores/vs_1$#', $url) && $request->method() === 'GET') {
                    return Http::response(['id' => 'vs_1'], 200);
                }
                if (preg_match('#/vector_stores/vs_1$#', $url) && $request->method() === 'DELETE') {
                    return Http::response(['id' => 'vs_1', 'deleted' => true], 200);
                }
                if (preg_match('#/vector_stores/vs_1/files$#', $url) && $request->method() === 'POST') {
                    return Http::response(['id' => 'file_vs_1', 'status' => 'attached'], 200);
                }
                if (preg_match('#/vector_stores/vs_1/files$#', $url) && $request->method() === 'GET') {
                    return Http::response(['data' => [['id' => 'file_vs_1']]], 200);
                }
                if (preg_match('#/vector_stores/vs_1/files/file_vs_1$#', $url) && $request->method() === 'DELETE') {
                    return Http::response(['id' => 'file_vs_1', 'deleted' => true], 200);
                }
                return Http::response([], 404);
            },
        ]);

        $create = $this->postJson('/api/openai/vector-stores', ['name' => 'Store']);
        $create->assertOk()->assertJsonFragment(['id' => 'vs_1']);

        $list = $this->getJson('/api/openai/vector-stores');
        $list->assertOk()->assertJsonStructure(['data']);

        $get = $this->getJson('/api/openai/vector-stores/vs_1');
        $get->assertOk()->assertJsonFragment(['id' => 'vs_1']);

        $attach = $this->postJson('/api/openai/vector-stores/vs_1/files', ['fileId' => 'file_abc']);
        $attach->assertOk()->assertJsonFragment(['status' => 'attached']);

        $files = $this->getJson('/api/openai/vector-stores/vs_1/files');
        $files->assertOk()->assertJsonStructure(['data']);

        $delFile = $this->deleteJson('/api/openai/vector-stores/vs_1/files/file_vs_1');
        $delFile->assertOk()->assertJsonFragment(['deleted' => true]);

        $del = $this->deleteJson('/api/openai/vector-stores/vs_1');
        $del->assertOk()->assertJsonFragment(['deleted' => true]);
    }
}
