<?php

namespace LaravelSolo\LiveReload\Services;

class ConfigResolver
{
    public static function resolve(array $config)
    {
        $preset = isset($config['preset']) ? (string) $config['preset'] : 'laravel';
        $presets = isset($config['presets']) && is_array($config['presets']) ? $config['presets'] : [];

        if ($preset === '' || $preset === 'laravel' || ! isset($presets[$preset]) || ! is_array($presets[$preset])) {
            return $config;
        }

        $presetConfig = $presets[$preset];

        foreach (['watch_paths', 'watch_extensions'] as $key) {
            if (isset($presetConfig[$key]) && is_array($presetConfig[$key])) {
                $config[$key] = $presetConfig[$key];
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

        return $config;
    }
}
