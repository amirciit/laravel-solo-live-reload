<?php

namespace LaravelSolo\LiveReload\Services;

use Illuminate\Contracts\Foundation\Application;

class LiveReloadStatus
{
    protected $watcher;
    protected $signal;
    protected $filter;

    public function __construct(FileWatcher $watcher, ReloadSignal $signal, PathFilter $filter)
    {
        $this->watcher = $watcher;
        $this->signal = $signal;
        $this->filter = $filter;
    }

    public function report(Application $app)
    {
        $config = ConfigResolver::resolve(config('live-reload', []));
        $payload = $this->signal->read();
        $prefix = ltrim((string) live_reload_effective_route_prefix($config), '/');
        $tokenQuery = live_reload_client_token_query($config);
        $paths = $this->watcher->watchPaths();
        $ignorePaths = isset($config['ignore_paths']) ? $config['ignore_paths'] : [];
        $extensions = isset($config['watch_extensions']) ? $config['watch_extensions'] : [];
        $ignorePatterns = isset($config['ignore_patterns']) ? $config['ignore_patterns'] : [];
        $heartbeat = $this->signal->readHeartbeat();
        $heartbeatEpoch = isset($heartbeat['epoch']) ? (int) $heartbeat['epoch'] : null;
        $heartbeatAge = $heartbeatEpoch === null ? null : max(0, time() - $heartbeatEpoch);
        $heartbeatTtl = max(0, (int) config('live-reload.watcher_stale_ttl_seconds', 75));
        $watcherProcessRunning = $this->watcherIsRunning();
        $heartbeatStale = $watcherProcessRunning && $heartbeatAge !== null && $heartbeatAge > $heartbeatTtl && $heartbeatTtl > 0;
        $watcherHealthy = $watcherProcessRunning && ! $heartbeatStale;

        return [
            'enabled' => live_reload_enabled($app),
            'injection_enabled' => live_reload_injection_enabled($app),
            'environment' => $app->environment(),
            'preset' => isset($config['preset']) ? (string) $config['preset'] : 'laravel',
            'route_prefix' => '/' . live_reload_effective_route_prefix(),
            'route_secret_set' => trim((string) ($config['route_secret'] ?? '')) !== '',
            'poll_interval' => (int) config('live-reload.poll_interval', 800),
            'scan_interval' => (int) config('live-reload.scan_interval', 500),
            'debounce_ms' => (int) config('live-reload.debounce_ms', 300),
            'reload_delay_ms' => (int) config('live-reload.reload_delay_ms', 80),
            'inject_on_error_pages' => (bool) config('live-reload.inject_on_error_pages', true),
            'strict_allowlist' => (bool) ($config['strict_allowlist'] ?? false),
            'css_hot_reload' => (bool) config('live-reload.css_hot_reload', true),
            'overlay_enabled' => (bool) config('live-reload.overlay.enabled', true),
            'multi_tab_sync' => (bool) config('live-reload.multi_tab_sync', true),
            'desktop_notifications' => (bool) config('live-reload.desktop_notifications', false),
            'safe_mode' => (bool) ($config['safe_mode'] ?? true),
            'loopback_only' => (bool) ($config['enforce_loopback'] ?? false),
            'watcher_stale_ttl_seconds' => $heartbeatTtl,
            'access_token_set' => trim((string) ($config['access_token'] ?? '')) !== '',
            'allowed_client_ips' => array_values($config['allowed_client_ips'] ?? []),
            'allowed_hosts' => array_values($config['allowed_hosts'] ?? []),
            'watch_env_path' => base_path('.env'),
            'watch_env_file' => (bool) ($config['watch_env_file'] ?? false),
            'client_token_query' => $tokenQuery,
            'route_status_url' => url('/' . $prefix . '/status' . $tokenQuery),
            'route_version_url' => url('/' . $prefix . '/version' . $tokenQuery),
            'route_client_url' => url('/' . $prefix . '/client.js' . $tokenQuery),
            'watcher_heartbeat' => $heartbeat,
            'watcher_heartbeat_age_seconds' => $heartbeatAge,
            'watcher_heartbeat_stale' => $heartbeatStale,
            'watcher_healthy' => $watcherHealthy,
            'watcher_running' => $watcherProcessRunning,
            'watcher_pid' => $this->watcherPid(),
            'watcher_paused' => $this->signal->isPaused(),
            'watchable_files' => count($this->watcher->snapshot()),
            'storage_path' => $this->filter->relativePath(live_reload_storage_path()),
            'signal_file' => $this->filter->relativePath($this->signal->path()),
            'last_version' => $payload['version'],
            'last_changed_file' => $payload['changed_file'],
            'last_changed_type' => $payload['changed_type'],
            'last_changed_at' => $payload['changed_at'],
            'last_changes' => $this->signal->readHistory(),
            'watch_paths' => $this->filter->displayPaths($paths),
            'watch_extensions' => array_values($extensions),
            'ignore_paths' => $this->filter->displayPaths($ignorePaths),
            'ignore_patterns' => array_values($ignorePatterns),
        ];
    }

    public function watcherIsRunning()
    {
        $pid = $this->watcherPid();

        return $pid > 0 && $this->processIsRunning($pid);
    }

    public function watcherPid()
    {
        $pidPath = $this->signal->pidPath();

        if (! is_file($pidPath)) {
            return null;
        }

        $pid = (int) trim((string) @file_get_contents($pidPath));

        return $pid > 0 ? $pid : null;
    }

    protected function processIsRunning($pid)
    {
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && function_exists('exec')) {
            $output = [];
            @exec('tasklist /FI "PID eq ' . (int) $pid . '" /NH', $output);
            $text = implode("\n", $output);

            return strpos($text, (string) $pid) !== false;
        }

        return false;
    }
}
