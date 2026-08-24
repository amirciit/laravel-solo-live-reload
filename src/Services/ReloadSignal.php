<?php

namespace LaravelSolo\LiveReload\Services;

use DateTimeImmutable;
use RuntimeException;

class ReloadSignal
{
    protected $filter;

    public function __construct(PathFilter $filter)
    {
        $this->filter = $filter;
    }

    public function read()
    {
        $path = $this->path();

        if (! is_file($path)) {
            return $this->defaultPayload();
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return $this->defaultPayload();
        }

        $payload = json_decode($contents, true);

        if (! is_array($payload)) {
            return $this->defaultPayload();
        }

        return array_merge($this->defaultPayload(), [
            'version' => isset($payload['version']) ? (string) $payload['version'] : '0',
            'changed_file' => isset($payload['changed_file']) ? (string) $payload['changed_file'] : null,
            'changed_type' => isset($payload['changed_type']) ? (string) $payload['changed_type'] : null,
            'changed_at' => isset($payload['changed_at']) ? (string) $payload['changed_at'] : null,
        ]);
    }

    public function write($changedFile = null, $changedType = null)
    {
        $this->ensureStorageDirectory();

        $payload = [
            'version' => $this->newVersion(),
            'changed_file' => $changedFile ? $this->filter->relativePath($changedFile) : null,
            'changed_type' => $changedType ? (string) $changedType : null,
            'changed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode live reload signal JSON.');
        }

        $temporary = $this->path() . '.tmp';

        if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write live reload signal file.');
        }

        if (! @rename($temporary, $this->path())) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace live reload signal file.');
        }

        $this->writeHistory($payload);

        return $payload;
    }

    public function clear()
    {
        foreach ([$this->path(), $this->path() . '.tmp', $this->historyPath(), $this->historyPath() . '.tmp', $this->pidPath(), $this->heartbeatPath(), $this->pausePath()] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function writeHeartbeat(array $extra = [])
    {
        $payload = [
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            'epoch' => time(),
            'pid' => (string) getmypid(),
        ];

        if (count($extra) > 0) {
            $payload = array_merge($payload, $extra);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return $this->readHeartbeat();
        }

        $temporary = $this->heartbeatPath() . '.tmp';

        if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            return $payload;
        }

        if (! @rename($temporary, $this->heartbeatPath())) {
            @unlink($temporary);
        }

        return $payload;
    }

    public function readHeartbeat()
    {
        $path = $this->heartbeatPath();

        if (! is_file($path)) {
            return [
                'timestamp' => null,
                'epoch' => null,
                'pid' => null,
            ];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [
                'timestamp' => null,
                'epoch' => null,
                'pid' => null,
            ];
        }

        $payload = json_decode($contents, true);

        if (! is_array($payload)) {
            return [
                'timestamp' => null,
                'epoch' => null,
                'pid' => null,
            ];
        }

        return array_merge([
            'timestamp' => null,
            'epoch' => null,
            'pid' => null,
        ], $payload);
    }

    public function heartbeatAgeSeconds()
    {
        $heartbeat = $this->readHeartbeat();
        $epoch = isset($heartbeat['epoch']) ? (int) $heartbeat['epoch'] : null;

        if (! $epoch) {
            return null;
        }

        return max(0, time() - $epoch);
    }

    public function isHeartbeatStale($ttlSeconds = null)
    {
        $ttlSeconds = (int) ($ttlSeconds ?? config('live-reload.watcher_stale_ttl_seconds', 75));

        if ($ttlSeconds <= 0) {
            return false;
        }

        $age = $this->heartbeatAgeSeconds();

        return $age === null ? false : $age > $ttlSeconds;
    }

    public function cleanupStaleWatcherState($ttlSeconds = null)
    {
        $ttlSeconds = (int) ($ttlSeconds ?? config('live-reload.watcher_stale_ttl_seconds', 75));
        $pid = $this->watcherPid();

        if (! $pid) {
            return false;
        }

        if (! $this->isWatcherProcessRunning($pid)) {
            @unlink($this->pidPath());
            if (is_file($this->pausePath())) {
                @unlink($this->pausePath());
            }

            return true;
        }

        if ($ttlSeconds <= 0 || ! $this->isHeartbeatStale($ttlSeconds)) {
            return false;
        }

        return false;
    }

    public function stopWatcher($force = false)
    {
        $pid = $this->watcherPid();

        if (! $pid) {
            return true;
        }

        if (! $this->isWatcherProcessRunning($pid)) {
            @unlink($this->pidPath());
            if (is_file($this->pausePath())) {
                @unlink($this->pausePath());
            }

            return true;
        }

        if (! $force && ! $this->isHeartbeatStale((int) config('live-reload.watcher_stale_ttl_seconds', 75))) {
            return false;
        }

        $this->terminateProcess($pid);

        if (! $this->waitForProcessExit($pid, 3)) {
            return false;
        }

        @unlink($this->pidPath());
        if (is_file($this->pausePath())) {
            @unlink($this->pausePath());
        }

        return true;
    }

    public function watcherPid()
    {
        if (! is_file($this->pidPath())) {
            return null;
        }

        $pid = (int) trim((string) @file_get_contents($this->pidPath()));

        return $pid > 0 ? $pid : null;
    }

    public function isWatcherProcessRunning($pid = null)
    {
        if ($pid === null) {
            $pid = $this->watcherPid();
        }

        if (! $pid || $pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill((int) $pid, 0);
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && function_exists('exec')) {
            $output = [];
            @exec('tasklist /FI "PID eq ' . (int) $pid . '" /NH', $output);
            $text = implode("\n", $output);

            return strpos($text, (string) $pid) !== false;
        }

        return false;
    }

    public function isPaused()
    {
        return is_file($this->pausePath());
    }

    public function pause()
    {
        $this->ensureStorageDirectory();
        @file_put_contents($this->pausePath(), '1', LOCK_EX);
    }

    public function resume()
    {
        if (is_file($this->pausePath())) {
            @unlink($this->pausePath());
        }
    }

    public function pidPath()
    {
        return live_reload_storage_path('watcher.pid');
    }

    public function heartbeatPath()
    {
        return live_reload_storage_path('heartbeat.json');
    }

    public function pausePath()
    {
        return live_reload_storage_path('watcher.paused');
    }

    public function path()
    {
        return live_reload_storage_path('reload.json');
    }

    public function historyPath()
    {
        return live_reload_storage_path('history.json');
    }

    public function readHistory()
    {
        $path = $this->historyPath();

        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $history = json_decode($contents, true);

        if (! is_array($history)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($payload) {
            if (! is_array($payload)) {
                return null;
            }

            return array_merge($this->defaultPayload(), [
                'version' => isset($payload['version']) ? (string) $payload['version'] : '0',
                'changed_file' => isset($payload['changed_file']) ? (string) $payload['changed_file'] : null,
                'changed_type' => isset($payload['changed_type']) ? (string) $payload['changed_type'] : null,
                'changed_at' => isset($payload['changed_at']) ? (string) $payload['changed_at'] : null,
            ]);
        }, $history)));
    }

    public function ensureStorageDirectory()
    {
        $directory = live_reload_storage_path();

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create live reload storage directory: ' . $directory);
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('Live reload storage directory is not writable: ' . $directory);
        }

        $gitignore = $directory . DIRECTORY_SEPARATOR . '.gitignore';

        if (! is_file($gitignore)) {
            @file_put_contents($gitignore, '*' . PHP_EOL . '!.gitignore' . PHP_EOL, LOCK_EX);
        }
    }

    protected function defaultPayload()
    {
        return [
            'version' => '0',
            'changed_file' => null,
            'changed_type' => null,
            'changed_at' => null,
        ];
    }

    protected function newVersion()
    {
        return (string) (int) floor(microtime(true) * 1000);
    }

    protected function writeHistory(array $payload)
    {
        $limit = max(0, (int) config('live-reload.status_history_limit', 10));

        if ($limit === 0) {
            return;
        }

        $history = $this->readHistory();
        array_unshift($history, $payload);
        $history = array_slice($history, 0, $limit);

        $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $temporary = $this->historyPath() . '.tmp';

        if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            return;
        }

        if (! @rename($temporary, $this->historyPath())) {
            @unlink($temporary);
        }
    }

    protected function terminateProcess(int $pid)
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 15);
            return;
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && function_exists('exec')) {
            $command = 'taskkill /PID ' . (int) $pid . ' /F /T';
            @exec($command);
            return;
        }
    }

    protected function waitForProcessExit($pid, $seconds = 3)
    {
        $deadline = time() + max(1, (int) $seconds);

        while (time() < $deadline) {
            if (! $this->isWatcherProcessRunning($pid)) {
                return true;
            }

            usleep(100000);
        }

        return ! $this->isWatcherProcessRunning($pid);
    }
}
