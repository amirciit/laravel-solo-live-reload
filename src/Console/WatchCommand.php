<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\FileWatcher;
use LaravelSolo\LiveReload\Services\PathFilter;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use RuntimeException;

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

        $this->watcher->watch(function ($change) {
            $this->line('Changed: ' . $change['path']);
            $this->info('Reload signal sent.');
        }, function ($message) {
            $this->warn($message);
        });

        return self::SUCCESS;
    }

    protected function watcherAlreadyRunning()
    {
        $pidPath = $this->signal->pidPath();

        if (! is_file($pidPath)) {
            return false;
        }

        $pid = (int) trim((string) @file_get_contents($pidPath));

        if ($pid <= 0) {
            @unlink($pidPath);

            return false;
        }

        if ($this->processIsRunning($pid)) {
            return true;
        }

        @unlink($pidPath);

        return false;
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
}
