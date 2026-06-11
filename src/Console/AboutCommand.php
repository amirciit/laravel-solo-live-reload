<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;

class AboutCommand extends Command
{
    protected $signature = 'live-reload:about';

    protected $description = 'Show Laravel Solo Live Reload package information.';

    protected $status;

    public function __construct(LiveReloadStatus $status)
    {
        parent::__construct();

        $this->status = $status;
    }

    public function handle()
    {
        $report = $this->status->report($this->laravel);

        $this->info('Laravel Solo Live Reload');
        $this->table(['Item', 'Value'], [
            ['Package', 'laravel-solo/live-reload'],
            ['Description', 'PHP-only live reload for Laravel local development'],
            ['Environment', $report['environment']],
            ['Enabled', $report['enabled'] ? 'yes' : 'no'],
            ['Preset', $report['preset']],
            ['Route prefix', $report['route_prefix']],
            ['Status URL', url($report['route_prefix'] . '/status')],
            ['Config path', 'config/live-reload.php'],
            ['Storage path', $report['storage_path']],
        ]);

        return self::SUCCESS;
    }
}
