<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\FileWatcher;
use LaravelSolo\LiveReload\Services\PathFilter;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use RuntimeException;
use Symfony\Component\Process\Process;

class WatchCommand extends Command
{
    protected $signature = 'live-reload:watch
        {--url=http://127.0.0.1:8000 : Local development URL shown in the watcher output}
        {--once : Build one snapshot and exit without starting the long-running watcher}';

    protected $description = 'Start the PHP live reload file watcher.';

    protected $watcher;
    protected $signal;
    protected $filter;

    public function __construct(FileWatcher $watcher, ReloadSignal $signal, PathFilter $filter)
    {
        parent::__construct();

        $this->watcher = $watcher;
        $this->signal = $signal;
        $this->filter = $filter;
    }

    public function handle()
    {
        if (! live_reload_enabled($this->laravel)) {
            $this->warn('Laravel Solo Live Reload is disabled for this environment.');

            return self::SUCCESS;
        }

        try {
            $this->signal->ensureStorageDirectory();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $cleanupTtl = (int) config('live-reload.watcher_stale_ttl_seconds', 75);
        if ($this->signal->cleanupStaleWatcherState($cleanupTtl)) {
            $this->line('Removed stale watcher state from an old process.');
        }

        if ($this->option('once')) {
            $count = count($this->watcher->snapshot());
            $this->info('Laravel Solo Live Reload scan completed.');
            $this->line('Watchable files: ' . $count);

            return self::SUCCESS;
        }

        if ($this->watcherAlreadyRunning()) {
            $this->error('Laravel Solo Live Reload watcher is already running.');

            return self::FAILURE;
        }

        $this->writePidFile();
        $this->registerShutdownHandler();

        if ($this->signal->read()['version'] === '0') {
            $this->signal->write(null);
        }

        $paths = $this->watcher->watchPaths();

        $this->info('Laravel Solo Live Reload started.');
        $this->line('Watching ' . count($paths) . ' paths.');
        $this->line('URL: ' . $this->option('url'));
        $this->line('Press CTRL+C to stop.');

        $healthInterval = 20;
        $heartbeatCounter = 0;

        try {
            $this->watcher->watch(function ($change) {
                $this->line('Changed: ' . $change['path']);
                $this->info('Reload signal sent.');
            }, function ($message) {
                $this->warn($message);
            }, function ($heartbeat) use (&$heartbeatCounter, $healthInterval) {
                $heartbeatCounter++;

                if ($heartbeatCounter % $healthInterval !== 0) {
                    return;
                }

                $state = $heartbeat['state'] ?? 'unknown';
                $scanCount = isset($heartbeat['scan_count']) ? (int) $heartbeat['scan_count'] : 0;

                $this->line('[LiveReload] Watcher status: ' . $state . ' (scan ' . $scanCount . ')');
            });
        } catch (\Throwable $exception) {
            $this->error('Watcher crashed: ' . $exception->getMessage());
            $this->notifyWatcherCrash($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function watcherAlreadyRunning()
    {
        if (! is_file($this->signal->pidPath())) {
            return false;
        }

        $pid = $this->signal->watcherPid();

        if (! $pid) {
            @unlink($this->signal->pidPath());

            return false;
        }

        if ($this->signal->isWatcherProcessRunning($pid)) {
            return true;
        }

        @unlink($this->signal->pidPath());

        return false;
    }

    protected function writePidFile()
    {
        file_put_contents($this->signal->pidPath(), (string) getmypid(), LOCK_EX);
    }

    protected function registerShutdownHandler()
    {
        $pidPath = $this->signal->pidPath();
        $pid = (string) getmypid();

        register_shutdown_function(function () use ($pidPath, $pid) {
            if (is_file($pidPath) && trim((string) @file_get_contents($pidPath)) === $pid) {
                @unlink($pidPath);
            }
        });
    }

    protected function notifyWatcherCrash($message)
    {
        $title = 'Laravel Solo Live Reload';
        $body = 'Watcher crashed: ' . $message;

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $messageArgument = str_replace("'", "''", $body);

            $process = new Process([
                'powershell',
                '-NoProfile',
                '-Command',
                "[void][System.Reflection.Assembly]::LoadWithPartialName('System.Windows.Forms'); [System.Windows.Forms.MessageBox]::Show('$messageArgument', '$title')",
            ]);

            try {
                $process->run();
            } catch (\Throwable $exception) {
                // Ignore notification failures; logging remains visible in terminal.
            }

            return;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $process = new Process([
                'osascript',
                '-e',
                'display notification "' . addslashes($body) . '" with title "' . addslashes($title) . '"',
            ]);

            try {
                $process->run();
            } catch (\Throwable $exception) {
                // Ignore notification failures; logging remains visible in terminal.
            }

            return;
        }

        $process = new Process([
            'notify-send',
            $title,
            $body,
        ]);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            // Ignore notification failures; logging remains visible in terminal.
        }
    }
}
