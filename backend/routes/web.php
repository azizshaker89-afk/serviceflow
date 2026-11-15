<?php

use Illuminate\Support\Facades\Route;

/*
| In production the built Vue SPA is copied to public/spa.html (+ public/assets) and served
| from the same origin as the API, so Sanctum's session cookie needs no CORS. Every path that
| is not the API, Sanctum or the health check returns the SPA and Vue Router takes over.
| In development Vite serves the SPA on :5173, so this falls back to Laravel's welcome page.
*/
Route::get('/{path?}', function () {
    $spa = public_path('spa.html');

    return is_file($spa)
        ? response()->file($spa, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache'])
        : view('welcome');
})->where('path', '^(?!api/|sanctum/|up$).*$')->name('spa');
