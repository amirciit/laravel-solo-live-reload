<?php

namespace LaravelSolo\LiveReload\Services;

class PathFilter
{
    protected $config;
    protected $basePath;
    protected $extensions;
    protected $ignorePaths;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->basePath = $this->normalize(base_path());
        $this->extensions = $this->normalizeExtensions(isset($config['watch_extensions']) ? $config['watch_extensions'] : []);
        $this->ignorePaths = $this->normalizePaths(isset($config['ignore_paths']) ? $config['ignore_paths'] : []);
    }

    public function shouldWatch($path)
    {
        if ($this->isIgnored($path)) {
            return false;
        }

        return $this->hasAllowedExtension($path);
    }

    public function isIgnored($path)
    {
        $path = $this->normalize($path);

        foreach ($this->ignorePaths as $ignored) {
            if ($path === $ignored || $this->startsWith($path, $ignored . '/')) {
                return true;
            }
        }

        return false;
    }

    public function hasAllowedExtension($path)
    {
        $path = strtolower($this->normalize($path));

        foreach ($this->extensions as $extension) {
            if ($this->endsWith($path, '.' . $extension)) {
                return true;
            }
        }

        return false;
    }

    public function relativePath($path)
    {
        $path = $this->normalize($path);

        if ($path === $this->basePath) {
            return '.';
        }

        if ($this->startsWith($path, $this->basePath . '/')) {
            return ltrim(substr($path, strlen($this->basePath)), '/');
        }

        return basename($path);
    }

    public function displayPaths(array $paths)
    {
        $display = [];

        foreach ($paths as $path) {
            $display[] = $this->relativePath($path);
        }

        return $display;
    }

    public function normalize($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('#/+#', '/', $path);

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    protected function normalizeExtensions(array $extensions)
    {
        $normalized = [];

        foreach ($extensions as $extension) {
            $extension = strtolower(ltrim((string) $extension, '.'));

            if ($extension !== '') {
                $normalized[] = $extension;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function normalizePaths(array $paths)
    {
        $normalized = [];

        foreach ($paths as $path) {
            if ($path === null || $path === '') {
                continue;
            }

            $normalized[] = $this->normalize($path);
        }

        return array_values(array_unique($normalized));
    }

    protected function startsWith($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    protected function endsWith($haystack, $needle)
    {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }
}
