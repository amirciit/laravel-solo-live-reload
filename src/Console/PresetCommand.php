<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ConfigResolver;

class PresetCommand extends Command
{
    protected $signature = 'live-reload:preset
        {name? : Preset to apply to this project}
        {--clear : Clear the project preset override}
        {--persist : Persist the preset in config/live-reload.php}';

    protected $description = 'List, inspect, and set per-project Live Reload presets.';

    public function handle()
    {
        $config = (array) config('live-reload', []);
        $presets = isset($config['presets']) && is_array($config['presets']) ? $config['presets'] : [];
        $resolved = ConfigResolver::resolve($config);
        $current = isset($resolved['preset']) ? (string) $resolved['preset'] : 'laravel';
        $name = (string) $this->argument('name');
        $persist = (bool) $this->option('persist');

        if ($this->option('clear')) {
            ConfigResolver::clearRuntimeOverrides(['preset']);

            $this->info('Per-project preset override cleared.');

            return self::SUCCESS;
        }

        if ($name === '') {
            $this->info('Laravel Solo Live Reload presets');
            $this->table(
                ['Preset', 'Paths', 'Extensions'],
                $this->presetRows($presets)
            );

            $this->line('Current preset: ' . $current);

            return self::SUCCESS;
        }

        if (! isset($presets[$name]) || ! is_array($presets[$name])) {
            $this->error('Unknown preset: ' . $name);
            $this->line('Available presets: ' . implode(', ', array_keys($presets)));

            return self::FAILURE;
        }

        if (! $persist) {
            ConfigResolver::setRuntimeOverrides(['preset' => $name]);
            $this->info('Preset "' . $name . '" applied for this project runtime.');

            return self::SUCCESS;
        }

        if ($this->persistPresetInConfig($name)) {
            ConfigResolver::clearRuntimeOverrides(['preset']);
            $this->info('Preset "' . $name . '" persisted in config/live-reload.php.');

            return self::SUCCESS;
        }

        $this->error('Unable to persist preset "' . $name . '".');

        return self::FAILURE;
    }

    protected function presetRows(array $presets)
    {
        $rows = [];

        foreach ($presets as $name => $preset) {
            $paths = isset($preset['watch_paths']) ? $preset['watch_paths'] : [];
            $extensions = isset($preset['watch_extensions']) ? $preset['watch_extensions'] : [];

            $rows[] = [
                (string) $name,
                is_array($paths) ? implode(', ', $paths) : '-',
                is_array($extensions) ? implode(', ', $extensions) : '-',
            ];
        }

        return $rows;
    }

    protected function persistPresetInConfig($preset)
    {
        $path = config_path('live-reload.php');

        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        $pattern = "/('preset'\\s*=>\\s*)'[^']*'/";
        $replacement = "$1'" . addslashes((string) $preset) . "'";
        $updated = preg_replace($pattern, $replacement, $contents, 1);

        if ($updated === null || $updated === $contents) {
            $updated = preg_replace("/\\n\\s*'watch_paths'/", "\n    'preset' => '" . addslashes((string) $preset) . "',\n    'watch_paths'", $contents, 1, $count);

            if ($count !== 1) {
                return false;
            }
        }

        if (@file_put_contents($path, $updated, LOCK_EX) === false) {
            return false;
        }

        return true;
    }
}
