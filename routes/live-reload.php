<?php

use Illuminate\Support\Facades\Route;
use LaravelSolo\LiveReload\Http\Controllers\LiveReloadController;

$prefix = live_reload_effective_route_prefix();

Route::prefix($prefix)
    ->name('live-reload.')
    ->group(function () {
        Route::get('/version', [LiveReloadController::class, 'version'])->name('version');
        Route::get('/client.js', [LiveReloadController::class, 'client'])->name('client');
        Route::get('/status', [LiveReloadController::class, 'status'])->name('status');
    });
