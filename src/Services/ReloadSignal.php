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

        return $payload;
    }

    public function clear()
    {
        foreach ([$this->path(), $this->path() . '.tmp', $this->pidPath()] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function path()
    {
        return live_reload_storage_path('reload.json');
    }

    public function pidPath()
    {
        return live_reload_storage_path('watcher.pid');
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
}
