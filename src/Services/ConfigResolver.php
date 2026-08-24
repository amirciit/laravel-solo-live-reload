<?php

namespace LaravelSolo\LiveReload\Services;

use RuntimeException;

class ConfigResolver
{
    public static function resolve(array $config)
    {
        $config = static::mergeRuntimeConfig($config);
        $preset = isset($config['preset']) ? (string) $config['preset'] : 'laravel';
        $presets = isset($config['presets']) && is_array($config['presets']) ? $config['presets'] : [];

        if ($preset !== '' && $preset !== 'laravel' && isset($presets[$preset]) && is_array($presets[$preset])) {
            $presetConfig = $presets[$preset];

            foreach (['watch_paths', 'watch_extensions'] as $key) {
                if (isset($presetConfig[$key]) && is_array($presetConfig[$key])) {
                    $config[$key] = array_values(array_unique($presetConfig[$key]));
                }
            }

            if (isset($presetConfig['ignore_paths']) && is_array($presetConfig['ignore_paths'])) {
                $configuredIgnorePaths = isset($config['ignore_paths']) && is_array($config['ignore_paths'])
                    ? $config['ignore_paths']
                    : [];

                $config['ignore_paths'] = array_values(array_unique(array_merge(
                    $configuredIgnorePaths,
                    $presetConfig['ignore_paths']
                )));
            }

            if (isset($presetConfig['ignore_patterns']) && is_array($presetConfig['ignore_patterns'])) {
                $configuredPatterns = isset($config['ignore_patterns']) && is_array($config['ignore_patterns'])
                    ? $config['ignore_patterns']
                    : [];

                $config['ignore_patterns'] = array_values(array_unique(array_merge(
                    $configuredPatterns,
                    $presetConfig['ignore_patterns']
                )));
            }
        }

        if (! (bool) ($config['watch_env_file'] ?? false)) {
            $config['watch_paths'] = array_values(array_filter(
                isset($config['watch_paths']) && is_array($config['watch_paths']) ? $config['watch_paths'] : [],
                function ($path) {
                    return $path !== base_path('.env');
                }
            ));
        }

        if (! isset($config['watch_extensions']) || ! is_array($config['watch_extensions'])) {
            $config['watch_extensions'] = [];
        }

        if (! isset($config['ignore_paths']) || ! is_array($config['ignore_paths'])) {
            $config['ignore_paths'] = [];
        }

        if (! isset($config['ignore_patterns']) || ! is_array($config['ignore_patterns'])) {
            $config['ignore_patterns'] = [];
        }

        return $config;
    }

    public static function getRuntimeOverrides()
    {
        $path = static::runtimeConfigPath();

        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $payload = json_decode($contents, true);

        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }

    public static function setRuntimeOverrides(array $overrides)
    {
        $directory = dirname(static::runtimeConfigPath());

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create live reload runtime config directory.');
        }

        $payload = array_filter($overrides, function ($value) {
            return $value !== null;
        });

        if (@file_put_contents(static::runtimeConfigPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Unable to write live reload runtime overrides.');
        }
    }

    public static function clearRuntimeOverrides(array $keys = [])
    {
        $path = static::runtimeConfigPath();

        if (! is_file($path)) {
            return;
        }

        if (count($keys) === 0) {
            @unlink($path);

            return;
        }

        $overrides = static::getRuntimeOverrides();

        foreach ($keys as $key) {
            unset($overrides[$key]);
        }

        static::setRuntimeOverrides($overrides);
    }

    protected static function mergeRuntimeConfig(array $config)
    {
        $runtime = static::getRuntimeOverrides();

        if (count($runtime) > 0) {
            $config = array_replace_recursive((array) $config, $runtime);
        }

        return $config;
    }

    protected static function runtimeConfigPath()
    {
        return live_reload_storage_path('runtime-config.json');
    }
}
