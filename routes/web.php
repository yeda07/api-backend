<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('docs.auth')->group(function () {
    Route::get('/docs', fn () => response()->file(public_path('swagger/index.html')));
    Route::get('/swagger/index.html', fn () => response()->file(public_path('swagger/index.html')));
    Route::get('/openapi.yaml', fn () => response()->file(public_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]));
})->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
]);
