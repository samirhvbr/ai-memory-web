<?php

use App\Http\Controllers\AiMemoryController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
|
| The whole app is one read-only panel over the ai-memory SQLite index, so the
| panel lives at the root. {hexId} is lower(hex(id)), 32 chars: ai-memory ids
| are BLOB (UUIDv7), and a hex string is what survives a round trip through a
| URL. The `where` below is what keeps a malformed id from reaching a query.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->name('ai-memory.')->controller(AiMemoryController::class)
    ->where(['hexId' => '[0-9a-fA-F]{32}'])
    ->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/live', 'live')->name('live');   // JSON behind the dashboard's "live" mode
        Route::get('/projects', 'projects')->name('projects');
        Route::get('/projects/{hexId}', 'projectShow')->name('projects.show');
        Route::get('/workspaces', 'workspaces')->name('workspaces');
        Route::get('/pages', 'pages')->name('pages');
        Route::get('/pages/{hexId}', 'pageShow')->name('pages.show');
        Route::get('/sessions', 'sessions')->name('sessions');
        Route::get('/sessions/{hexId}', 'sessionShow')->name('sessions.show');
        Route::get('/observations', 'observations')->name('observations');
        Route::get('/observations/{hexId}', 'observationShow')->name('observations.show');
        Route::get('/handoffs', 'handoffs')->name('handoffs');
        Route::get('/handoffs/{hexId}', 'handoffShow')->name('handoffs.show');
        Route::get('/search', 'search')->name('search');
    });
