<?php

use Illuminate\Support\Facades\Route;
use LaravelSolo\LiveReload\Http\Controllers\LiveReloadController;

$prefix = trim((string) config('live-reload.route_prefix', '__live-reload'), '/');

Route::prefix($prefix)
    ->name('live-reload.')
    ->group(function () {
        Route::get('/version', [LiveReloadController::class, 'version'])->name('version');
        Route::get('/client.js', [LiveReloadController::class, 'client'])->name('client');
        Route::get('/status', [LiveReloadController::class, 'status'])->name('status');
    });
