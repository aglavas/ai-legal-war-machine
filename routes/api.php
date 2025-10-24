<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\McpOpenAIController;

Route::prefix('openai')->group(function () {
    Route::post('responses', [OpenAIController::class, 'responses']);
    Route::post('chat', [OpenAIController::class, 'chat']);
    Route::post('embeddings', [OpenAIController::class, 'embeddings']);
    Route::post('image', [OpenAIController::class, 'image']);
    Route::post('tts', [OpenAIController::class, 'tts']);
    Route::post('transcribe', [OpenAIController::class, 'transcribe']);

    Route::post('files', [OpenAIController::class, 'filesUpload']);
    Route::get('files', [OpenAIController::class, 'filesList']);
    Route::delete('files/{fileId}', [OpenAIController::class, 'filesDelete']);

    // Assistants
    Route::post('assistants', [OpenAIController::class, 'assistantsCreate']);
    Route::get('assistants', [OpenAIController::class, 'assistantsList']);
    Route::get('assistants/{assistantId}', [OpenAIController::class, 'assistantsRetrieve']);
    Route::delete('assistants/{assistantId}', [OpenAIController::class, 'assistantsDelete']);

    // Vector Stores
    Route::post('vector-stores', [OpenAIController::class, 'vectorStoreCreate']);
    Route::get('vector-stores', [OpenAIController::class, 'vectorStoreList']);
    Route::get('vector-stores/{storeId}', [OpenAIController::class, 'vectorStoreRetrieve']);
    Route::delete('vector-stores/{storeId}', [OpenAIController::class, 'vectorStoreDelete']);
    Route::post('vector-stores/{storeId}/files', [OpenAIController::class, 'vectorStoreAddFile']);
    Route::get('vector-stores/{storeId}/files', [OpenAIController::class, 'vectorStoreListFiles']);
    Route::delete('vector-stores/{storeId}/files/{fileId}', [OpenAIController::class, 'vectorStoreDeleteFile']);
});

Route::prefix('ingest')->group(function () {
    Route::post('text', [IngestController::class, 'ingestText']);
    Route::post('file', [IngestController::class, 'ingestFile']);
    Route::post('search', [IngestController::class, 'search']);
    Route::post('laws', [IngestController::class, 'ingestLaws']);
});

Route::prefix('uploads')->group(function () {
    // direct upload
    Route::post('/', [UploadController::class, 'direct']);

    // chunked upload
    Route::post('start', [UploadController::class, 'start']);
    Route::post('{uploadId}/chunk/{index}', [UploadController::class, 'chunk'])
        ->where(['index' => '[0-9]+']);
    Route::post('{uploadId}/complete', [UploadController::class, 'complete']);
    Route::delete('{uploadId}', [UploadController::class, 'cancel']);
});

// MCP-OpenAI Bridge: Exposes MCP tools as OpenAI-compatible function calling endpoints
Route::prefix('mcp-openai')->group(function () {
    // Public info endpoint (no auth required, with rate limiting)
    Route::get('info', [McpOpenAIController::class, 'info'])
        ->middleware('throttle:60,1');

    // Protected endpoints - require API token authentication and rate limiting
    Route::middleware(['mcp.auth', 'throttle:60,1'])->group(function () {
        // Tool discovery and execution
        Route::get('tools', [McpOpenAIController::class, 'listTools']);
        Route::post('tools/execute', [McpOpenAIController::class, 'executeTool']);

        // OpenAI-compatible chat completions with automatic MCP tools injection
        Route::post('chat/completions', [McpOpenAIController::class, 'chatCompletions']);

        // Webhook endpoint for OpenAI function calling callbacks
        Route::post('webhook', [McpOpenAIController::class, 'webhook']);
    });
});
