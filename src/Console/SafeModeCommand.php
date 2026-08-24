<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ConfigResolver;

class SafeModeCommand extends Command
{
    protected $signature = 'live-reload:safe-mode
        {--off : Disable loopback-only blocking for this project}';

    protected $description = 'Enable or disable live reload safe-mode restrictions for local tunnels.';

    public function handle()
    {
        if ($this->option('off')) {
            ConfigResolver::setRuntimeOverrides([
                'safe_mode' => false,
                'enforce_loopback' => false,
            ]);

            $this->info('Safe mode disabled in runtime overrides.');
            $this->warn('Live reload endpoints may now be reachable from non-loopback hosts.');

            return self::SUCCESS;
        }

        $secret = trim((string) config('live-reload.route_secret', ''));

        if ($secret === '') {
            $secret = bin2hex(random_bytes(12));
            $this->line('Generated hidden route secret for safe-mode protection.');
        }

        ConfigResolver::setRuntimeOverrides([
            'safe_mode' => true,
            'enforce_loopback' => true,
            'route_secret' => $secret,
        ]);

        $this->info('Safe mode enabled in runtime overrides.');
        $this->line('Live reload endpoints are now restricted to loopback unless explicitly allowed.');

        return self::SUCCESS;
    }
}
