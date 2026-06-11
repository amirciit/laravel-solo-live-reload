<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;

class StatusCommand extends Command
{
    protected $signature = 'live-reload:status';

    protected $description = 'Show Laravel Solo Live Reload status and configuration.';

    protected $status;

    public function __construct(LiveReloadStatus $status)
    {
        parent::__construct();

        $this->status = $status;
    }

    public function handle()
    {
        $report = $this->status->report($this->laravel);

        $this->info('Laravel Solo Live Reload status');
        $this->table(['Setting', 'Value'], [
            ['Enabled', $report['enabled'] ? 'yes' : 'no'],
            ['Environment', $report['environment']],
            ['Preset', $report['preset']],
            ['Watcher running', $report['watcher_running'] ? 'yes' : 'no'],
            ['Watchable files', (string) $report['watchable_files']],
            ['Poll interval', $report['poll_interval'] . ' ms'],
            ['Scan interval', $report['scan_interval'] . ' ms'],
            ['Debounce', $report['debounce_ms'] . ' ms'],
            ['Route prefix', $report['route_prefix']],
            ['Signal file', $report['signal_file']],
            ['CSS hot reload', $report['css_hot_reload'] ? 'yes' : 'no'],
            ['Overlay', $report['overlay_enabled'] ? 'yes' : 'no'],
            ['Multi-tab sync', $report['multi_tab_sync'] ? 'yes' : 'no'],
            ['Last version', $report['last_version']],
            ['Last changed file', $report['last_changed_file'] ?: 'none'],
            ['Last changed type', $report['last_changed_type'] ?: 'none'],
            ['Last changed at', $report['last_changed_at'] ?: 'never'],
        ]);

        $this->line('Watched paths:');
        foreach ($report['watch_paths'] as $path) {
            $this->line(' - ' . $path);
        }

        $this->line('Watched extensions: ' . implode(', ', $report['watch_extensions']));

        $this->line('Ignored paths:');
        foreach ($report['ignore_paths'] as $path) {
            $this->line(' - ' . $path);
        }

        return self::SUCCESS;
    }
}
