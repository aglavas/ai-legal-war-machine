<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/uploader', 'uploader');
Route::view('/timeline', 'timeline');
Route::view('/comparative-timeline', 'comparative-timeline');
Route::view('/openai/logs', 'openai-logs');
