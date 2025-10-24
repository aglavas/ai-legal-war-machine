<?php

use App\Http\Controllers\EvidenceAssetController;
use App\Http\Controllers\McpHttpController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('dashboard');
});

Route::view('/uploader', 'uploader');
Route::get('/timeline', \App\Http\Livewire\TimelinePage::class);  //dobar
Route::get('/comparative-timeline3', \App\Http\Livewire\ComparativeTimelinePage::class); //stari dobar
Route::get('/comparative-timeline', \App\Http\Livewire\GupTimeline::class);;
Route::view('/openai/logs', 'openai-logs');
Route::view('/openai/responses', 'openai-responses')->name('openai.responses');

Route::view('/ingested-laws', 'ingested-laws')->name('ingested-laws.index');
Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::get('/evidence/asset', EvidenceAssetController::class)
    ->middleware('signed')
    ->name('evidence.asset');

// New transcript preview page
Route::view('/transcript', 'transcript')->name('transcript');

// Textract Pipeline Manager
Route::view('/textract', 'textract')->name('textract.manager');

// e-Oglasna monitoring dashboard
Route::get('/eoglasna', \App\Http\Livewire\EoglasnaMonitoring::class)->name('eoglasna.monitoring');

// MCP HTTP Endpoint - for Vizra ADK agents (OdlukeAgent) and external MCP clients
Route::prefix('mcp')->group(function () {
    // Info endpoint is public with rate limiting
    Route::get('/info', [McpHttpController::class, 'info'])
        ->middleware('throttle:60,1')
        ->name('mcp.info');

    // Message endpoint requires authentication and rate limiting
    Route::post('/message', [McpHttpController::class, 'message'])
        ->middleware(['mcp.auth', 'throttle:60,1'])
        ->name('mcp.message');
});
