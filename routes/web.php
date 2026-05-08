<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('docs.auth')->group(function () {
    Route::get('/docs', fn () => response()->file(public_path('swagger/index.html')));
    Route::get('/swagger/index.html', fn () => response()->file(public_path('swagger/index.html')));
    Route::get('/openapi.yaml', fn () => response()->file(public_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]));
});
