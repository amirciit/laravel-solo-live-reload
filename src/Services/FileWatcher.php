<?php

namespace LaravelSolo\LiveReload\Services;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class FileWatcher
{
    protected $filter;
    protected $signal;
    protected $config;
    protected $running = false;

    public function __construct(PathFilter $filter, ReloadSignal $signal, array $config = [])
    {
        $this->filter = $filter;
        $this->signal = $signal;
        $this->config = $config;
    }

    public function watch(?callable $onChange = null, ?callable $onError = null, ?callable $onHeartbeat = null)
    {
        $this->running = true;
        $previous = $this->snapshot($onError);
        $scanCount = 0;
        $wasPaused = false;

        while ($this->running) {
            $scanCount++;
            $heartbeat = $this->signal->writeHeartbeat([
                'scan_count' => $scanCount,
                'paused' => $this->signal->isPaused(),
            ]);

            if ($this->signal->isPaused()) {
                if (! $wasPaused) {
                    if ($onError !== null) {
                        $onError('Watcher paused.');
                    }

                    $wasPaused = true;
                }

                if ($onHeartbeat !== null) {
                    call_user_func($onHeartbeat, [
                        'scan_count' => $scanCount,
                        'heartbeat' => $heartbeat,
                        'state' => 'paused',
                    ]);
                }

                $this->sleep($this->scanInterval());

                continue;
            }

            if ($wasPaused) {
                if ($onError !== null) {
                    $onError('Watcher resumed.');
                }

                $wasPaused = false;
            }

            if ($onHeartbeat !== null) {
                call_user_func($onHeartbeat, [
                    'scan_count' => $scanCount,
                    'heartbeat' => $heartbeat,
                    'state' => 'running',
                ]);
            }

            $this->sleep($this->scanInterval());

            $current = $this->snapshot($onError);
            $changes = $this->detectChanges($previous, $current);

            if (count($changes) === 0) {
                $previous = $current;
                continue;
            }

            $this->sleep($this->debounceMs());

            $debounced = $this->snapshot($onError);
            $changes = array_merge($changes, $this->detectChanges($current, $debounced));
            $change = $this->firstChange($changes);

            if ($change !== null) {
                $payload = $this->signal->write($change['path'], $change['type']);

                if ($onChange !== null) {
                    call_user_func($onChange, $change, $payload);
                }
            }

            $previous = $debounced;
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    public function snapshot(?callable $onError = null)
    {
        $snapshot = [];

        foreach ($this->watchPaths() as $path) {
            $this->scanPath($path, $snapshot, $onError);
        }

        ksort($snapshot);

        return $snapshot;
    }

    public function detectChanges(array $previous, array $current)
    {
        $changes = [];

        foreach ($current as $path => $fingerprint) {
            if (! array_key_exists($path, $previous)) {
                $changes[] = [
                    'type' => 'created',
                    'path' => $path,
                ];

                continue;
            }

            if ($previous[$path] !== $fingerprint) {
                $changes[] = [
                    'type' => 'updated',
                    'path' => $path,
                ];
            }
        }

        foreach ($previous as $path => $fingerprint) {
            if (! array_key_exists($path, $current)) {
                $changes[] = [
                    'type' => 'deleted',
                    'path' => $path,
                ];
            }
        }

        return $changes;
    }

    public function watchPaths()
    {
        $paths = isset($this->config['watch_paths']) ? $this->config['watch_paths'] : [];

        return array_values(array_filter($paths, function ($path) {
            return $path !== null && $path !== '';
        }));
    }

    protected function scanPath($path, array &$snapshot, ?callable $onError = null)
    {
        if (! file_exists($path)) {
            return;
        }

        if ($this->filter->isIgnored($path)) {
            return;
        }

        if (is_file($path)) {
            $this->addFileToSnapshot($path, $snapshot, $onError);

            return;
        }

        if (! is_dir($path) || ! is_readable($path)) {
            $this->reportError($onError, 'Path is not readable: ' . $path);

            return;
        }

        try {
            $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator($directory, function ($current) {
                $pathname = $current->getPathname();

                if ($this->filter->isIgnored($pathname)) {
                    return false;
                }

                if ($current->isDir()) {
                    return $current->isReadable();
                }

                return $this->filter->shouldWatch($pathname);
            });

            $iterator = new RecursiveIteratorIterator($filter);

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $this->addFileToSnapshot($file->getPathname(), $snapshot, $onError);
                }
            }
        } catch (UnexpectedValueException $exception) {
            $this->reportError($onError, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->reportError($onError, $exception->getMessage());
        }
    }

    protected function addFileToSnapshot($path, array &$snapshot, ?callable $onError = null)
    {
        if (! $this->filter->shouldWatch($path)) {
            return;
        }

        if (! is_readable($path)) {
            $this->reportError($onError, 'File is not readable: ' . $path);

            return;
        }

        $mtime = @filemtime($path);
        $size = @filesize($path);

        if ($mtime === false) {
            $this->reportError($onError, 'Unable to read file modification time: ' . $path);

            return;
        }

        $snapshot[$this->filter->relativePath($path)] = $mtime . ':' . ($size === false ? '0' : $size);
    }

    protected function scanInterval()
    {
        return max(100, (int) (isset($this->config['scan_interval']) ? $this->config['scan_interval'] : 500));
    }

    protected function debounceMs()
    {
        return max(0, (int) (isset($this->config['debounce_ms']) ? $this->config['debounce_ms'] : 300));
    }

    protected function sleep($milliseconds)
    {
        usleep($milliseconds * 1000);
    }

    protected function firstChange(array $changes)
    {
        if (count($changes) === 0) {
            return null;
        }

        return array_values($changes)[0];
    }

    protected function reportError(?callable $onError = null, $message = '')
    {
        if ($onError !== null) {
            call_user_func($onError, $message);
        }
    }
}
