<?php

namespace LaravelSolo\LiveReload\Services;

class PathFilter
{
    protected $config;
    protected $basePath;
    protected $extensions;
    protected $ignorePaths;
    protected $ignorePatterns;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->basePath = $this->normalize(base_path());
        $this->extensions = $this->normalizeExtensions(isset($config['watch_extensions']) ? $config['watch_extensions'] : []);
        $this->ignorePaths = $this->normalizePaths(isset($config['ignore_paths']) ? $config['ignore_paths'] : []);
        $this->ignorePatterns = array_values(array_unique(array_merge(
            $this->normalizePatterns(isset($config['ignore_patterns']) ? $config['ignore_patterns'] : []),
            $this->parseGitignorePatterns()
        )));
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

        $subject = $this->relativePath($path);

        foreach ($this->ignorePatterns as $pattern) {
            if ($this->matchesPattern($subject, $pattern)) {
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

    protected function normalizePatterns(array $patterns)
    {
        $normalized = [];

        foreach ($patterns as $pattern) {
            if ($pattern === null || $pattern === '') {
                continue;
            }

            $normalized[] = $this->normalizePattern((string) $pattern);
        }

        return array_values(array_unique($normalized));
    }

    protected function parseGitignorePatterns()
    {
        $path = base_path('.gitignore');

        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $patterns = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if ($line[0] === '!') {
                continue;
            }

            if ($line[0] === '/') {
                $line = ltrim($line, '/');
            }

            if ($line === '') {
                continue;
            }

            $patterns[] = $line;
        }

        return $this->normalizePatterns($patterns);
    }

    protected function normalizePattern($pattern)
    {
        $pattern = str_replace('\\', '/', (string) $pattern);
        $pattern = preg_replace('#/+#', '/', $pattern);

        return $pattern;
    }

    protected function matchesPattern($path, $pattern)
    {
        $subject = $this->normalize($path);
        $subject = ltrim((string) $subject, '/');
        $pattern = ltrim((string) $pattern, '/');

        if ($pattern === '') {
            return false;
        }

        if ($this->endsWith($pattern, '/')) {
            $pattern = rtrim($pattern, '/');

            return $subject === $pattern || $this->startsWith($subject, $pattern . '/');
        }

        if (strpos($pattern, '/') === false) {
            if (fnmatch($pattern, basename($subject), FNM_PATHNAME | FNM_CASEFOLD)) {
                return true;
            }
        }

        if ($this->isGlobPattern($pattern)) {
            return $this->globMatch($pattern, $subject);
        }

        return $subject === $pattern;
    }

    protected function isGlobPattern($pattern)
    {
        return strpos($pattern, '*') !== false || strpos($pattern, '?') !== false;
    }

    protected function globMatch($pattern, $path)
    {
        if (strpos($pattern, '**') !== false) {
            $regex = $this->globPatternToRegex($pattern);

            return preg_match($regex, $path) === 1;
        }

        return fnmatch($pattern, $path, FNM_PATHNAME | FNM_CASEFOLD);
    }

    protected function globPatternToRegex($pattern)
    {
        $placeholder = '__LR_GLOB_DS__';
        $pattern = str_replace('**', $placeholder, $pattern);
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace($placeholder, '.*', $pattern);
        $pattern = str_replace('\*', '[^/]*', $pattern);
        $pattern = str_replace('\\?', '.', $pattern);

        return '#^' . $pattern . '$#i';
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
