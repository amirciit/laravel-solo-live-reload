<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'live-reload:install {--force : Overwrite the existing configuration file}';

    protected $description = 'Publish the Laravel Solo Live Reload configuration file.';

    public function handle()
    {
        $this->call('vendor:publish', [
            '--tag' => 'live-reload-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Laravel Solo Live Reload installed.');
        $this->line('Config: config/live-reload.php');

        return self::SUCCESS;
    }
}
