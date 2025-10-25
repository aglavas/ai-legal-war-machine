<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\SearchController;

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


Route::prefix('agent')->group(function () {
    // Research runs
    Route::post('research/start', [\App\Http\Controllers\AgentController::class, 'startResearch']);
    Route::get('research', [\App\Http\Controllers\AgentController::class, 'listResearch']);
    Route::get('research/{id}', [\App\Http\Controllers\AgentController::class, 'getResearch']);
    Route::get('research/{id}/evaluation', [\App\Http\Controllers\AgentController::class, 'getEvaluation']);
    Route::delete('research/{id}', [\App\Http\Controllers\AgentController::class, 'deleteResearch']);
});

Route::prefix('search')->group(function () {
    // Unified search across all corpora
    Route::post('/', [SearchController::class, 'search']);

    // Corpus-specific search
    Route::post('/laws', [SearchController::class, 'searchLaws']);
    Route::post('/decisions', [SearchController::class, 'searchDecisions']);
    Route::post('/cases', [SearchController::class, 'searchCases']);

    // Advanced search
    Route::post('/hybrid', [SearchController::class, 'hybridSearch']);
    Route::post('/with-citations', [SearchController::class, 'searchWithCitations']);

});
