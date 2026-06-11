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
        $paths = $this->watcher->watchPaths();
        $ignorePaths = isset($config['ignore_paths']) ? $config['ignore_paths'] : [];
        $extensions = isset($config['watch_extensions']) ? $config['watch_extensions'] : [];

        return [
            'enabled' => live_reload_enabled($app),
            'injection_enabled' => live_reload_injection_enabled($app),
            'environment' => $app->environment(),
            'preset' => isset($config['preset']) ? (string) $config['preset'] : 'laravel',
            'route_prefix' => '/' . trim((string) config('live-reload.route_prefix', '__live-reload'), '/'),
            'poll_interval' => (int) config('live-reload.poll_interval', 800),
            'scan_interval' => (int) config('live-reload.scan_interval', 500),
            'debounce_ms' => (int) config('live-reload.debounce_ms', 300),
            'css_hot_reload' => (bool) config('live-reload.css_hot_reload', true),
            'overlay_enabled' => (bool) config('live-reload.overlay.enabled', true),
            'multi_tab_sync' => (bool) config('live-reload.multi_tab_sync', true),
            'desktop_notifications' => (bool) config('live-reload.desktop_notifications', false),
            'watcher_running' => $this->watcherIsRunning(),
            'watcher_pid' => $this->watcherPid(),
            'watchable_files' => count($this->watcher->snapshot()),
            'storage_path' => $this->filter->relativePath(live_reload_storage_path()),
            'signal_file' => $this->filter->relativePath($this->signal->path()),
            'last_version' => $payload['version'],
            'last_changed_file' => $payload['changed_file'],
            'last_changed_type' => $payload['changed_type'],
            'last_changed_at' => $payload['changed_at'],
            'watch_paths' => $this->filter->displayPaths($paths),
            'watch_extensions' => array_values($extensions),
            'ignore_paths' => $this->filter->displayPaths($ignorePaths),
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
