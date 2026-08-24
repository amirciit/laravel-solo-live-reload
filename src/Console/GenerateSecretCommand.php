<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ConfigResolver;

class GenerateSecretCommand extends Command
{
    protected $signature = 'live-reload:token:generate
        {--route : Generate/rotate LIVE_RELOAD_ROUTE_SECRET}
        {--length=12 : Token length in bytes}
        {--show-secret : Show the full generated secret value}';

    protected $description = 'Rotate live-reload access token or route secret.';

    public function handle()
    {
        $length = (int) max(8, $this->option('length'));
        $token = bin2hex(random_bytes($length));

        $isRouteSecret = (bool) $this->option('route');
        $key = $isRouteSecret ? 'route_secret' : 'access_token';
        $label = $isRouteSecret ? 'route secret' : 'access token';

        ConfigResolver::setRuntimeOverrides([$key => $token]);

        $this->info('Generated new ' . $label . ' in runtime overrides.');
        $this->line('Stored value: ' . $this->maskOrShow($token));

        if (! $this->option('show-secret')) {
            $this->warn('Use --show-secret to print full value once.');
        }

        return self::SUCCESS;
    }

    protected function maskOrShow($token)
    {
        if ($this->option('show-secret')) {
            return $token;
        }

        if (strlen($token) <= 10) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 4) . '...' . substr($token, -4);
    }
}
