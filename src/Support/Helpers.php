<?php

use Illuminate\Contracts\Foundation\Application;

if (! function_exists('live_reload_enabled')) {
    function live_reload_enabled(Application $app = null)
    {
        $app = $app ?: app();

        if (! (bool) config('live-reload.enabled', true)) {
            return false;
        }

        return ! $app->environment('production');
    }
}

if (! function_exists('live_reload_injection_enabled')) {
    function live_reload_injection_enabled(Application $app = null)
    {
        $app = $app ?: app();

        if (! live_reload_enabled($app)) {
            return false;
        }

        if (! (bool) config('live-reload.inject_script', true)) {
            return false;
        }

        return $app->environment(['local', 'development', 'testing']);
    }
}

if (! function_exists('live_reload_storage_path')) {
    function live_reload_storage_path($path = '')
    {
        $base = rtrim((string) config('live-reload.storage_path', storage_path('framework/live-reload')), DIRECTORY_SEPARATOR);

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}
