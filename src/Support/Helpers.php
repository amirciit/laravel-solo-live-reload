<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use LaravelSolo\LiveReload\Services\ConfigResolver;

if (! function_exists('live_reload_effective_config')) {
    function live_reload_effective_config()
    {
        return ConfigResolver::resolve((array) config('live-reload', []));
    }
}

if (! function_exists('live_reload_enabled')) {
    function live_reload_enabled(?Application $app = null)
    {
        $app = $app ?: app();

        if (! (bool) config('live-reload.enabled', true)) {
            return false;
        }

        return $app->environment('local');
    }
}

if (! function_exists('live_reload_injection_enabled')) {
    function live_reload_injection_enabled(?Application $app = null)
    {
        $app = $app ?: app();

        if (! live_reload_enabled($app)) {
            return false;
        }

        if (! (bool) config('live-reload.inject_script', true)) {
            return false;
        }

        return $app->environment('local');
    }
}

if (! function_exists('live_reload_storage_path')) {
    function live_reload_storage_path($path = '')
    {
        $base = rtrim((string) config('live-reload.storage_path', storage_path('framework/live-reload')), DIRECTORY_SEPARATOR);

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('live_reload_effective_route_prefix')) {
    function live_reload_effective_route_prefix($config = null)
    {
        $config = $config === null ? live_reload_effective_config() : (array) $config;
        $prefix = trim((string) ($config['route_prefix'] ?? '__live-reload'), '/');
        $secret = trim((string) ($config['route_secret'] ?? ''));

        if ($secret === '') {
            return $prefix;
        }

        return trim($prefix . '/' . $secret, '/');
    }
}

if (! function_exists('live_reload_access_token')) {
    function live_reload_access_token($config = null)
    {
        $config = $config === null ? live_reload_effective_config() : (array) $config;

        return (string) ($config['access_token'] ?? '');
    }
}

if (! function_exists('live_reload_client_token_query')) {
    function live_reload_client_token_query($config = null)
    {
        $token = trim((string) live_reload_access_token($config));

        if ($token === '') {
            return '';
        }

        return '?live_reload_token=' . rawurlencode($token);
    }
}

if (! function_exists('live_reload_extract_request_token')) {
    function live_reload_extract_request_token($request)
    {
        if (! ($request instanceof Request)) {
            return '';
        }

        $token = (string) $request->header('X-Live-Reload-Token', '');

        if ($token === '' && method_exists($request, 'bearerToken')) {
            $token = (string) $request->bearerToken();
        }

        if ($token === '') {
            $token = (string) $request->query('live_reload_token', '');
        }

        if ($token === '') {
            $token = (string) $request->query('token', '');
        }

        return $token;
    }
}

if (! function_exists('live_reload_request_is_allowed')) {
    function live_reload_request_is_allowed($request, $config = null)
    {
        if (! ($request instanceof Request)) {
            return false;
        }

        $config = $config === null ? live_reload_effective_config() : (array) $config;

        if (! live_reload_injection_enabled()) {
            return false;
        }

        $token = live_reload_access_token($config);

        if ($token !== '' && $token !== live_reload_extract_request_token($request)) {
            return false;
        }

        $allowedIps = (array) ($config['allowed_client_ips'] ?? []);
        $allowedHosts = (array) ($config['allowed_hosts'] ?? []);
        $enforceLoopback = (bool) ($config['enforce_loopback'] ?? true);
        $safeMode = (bool) ($config['safe_mode'] ?? true);
        $strictAllowlist = (bool) ($config['strict_allowlist'] ?? false);
        $host = strtolower((string) $request->getHost());
        $ip = (string) $request->ip();
        $lowerAllowedHosts = array_map('strtolower', $allowedHosts);
        $hasAllowlist = count($allowedIps) > 0 || count($lowerAllowedHosts) > 0;

        $ipAllowed = in_array($ip, $allowedIps, true);
        $hostAllowed = $host !== '' && in_array($host, $lowerAllowedHosts, true);
        $loopback = live_reload_is_loopback_address($ip);

        if ($strictAllowlist && $hasAllowlist) {
            return $ipAllowed || $hostAllowed;
        }

        if ($safeMode || $enforceLoopback) {
            if ($loopback || $ipAllowed || $hostAllowed) {
                return true;
            }

            return false;
        }

        if ($hasAllowlist) {
            return $ipAllowed || $hostAllowed;
        }

        return true;
    }
}

if (! function_exists('live_reload_is_loopback_address')) {
    function live_reload_is_loopback_address($ip)
    {
        $ip = (string) $ip;

        if ($ip === '' || $ip === 'localhost') {
            return true;
        }

        if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '127.') === 0 || strpos($ip, '::ffff:127.') === 0) {
            return true;
        }

        return false;
    }
}
