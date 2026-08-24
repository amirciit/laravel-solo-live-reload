<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;

class StatusCommand extends Command
{
    protected $signature = 'live-reload:status
        {--json : Output status as JSON}
        {--watch : Show live status updates}
        {--interval=2 : Seconds between watch updates}';

    protected $description = 'Show Laravel Solo Live Reload status and configuration.';

    protected $status;

    public function __construct(LiveReloadStatus $status)
    {
        parent::__construct();

        $this->status = $status;
    }

    public function handle()
    {
        if ($this->option('watch')) {
            return $this->watchStatus((int) $this->option('interval'));
        }

        $report = $this->status->report($this->laravel);
        $this->outputReport($report);

        return self::SUCCESS;
    }

    protected function watchStatus($intervalSeconds)
    {
        $intervalSeconds = max(1, $intervalSeconds);

        $isJson = (bool) $this->option('json');
        $count = 0;

        while (true) {
            $report = $this->status->report($this->laravel);
            ++$count;

            if ($isJson) {
                $this->line(json_encode([
                    'tick' => $count,
                    'status' => $report,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                if (function_exists('system')) {
                    @system('cls');
                    @system('clear');
                }

                $this->outputReport($report);
                $this->line('tick=' . $count . ' interval=' . $intervalSeconds . 's');
            }

            sleep($intervalSeconds);
        }
    }

    protected function outputReport(array $report)
    {
        $heartbeatAge = $report['watcher_heartbeat_age_seconds'];
        $heartbeatStatus = $heartbeatAge === null ? 'n/a' : $heartbeatAge . 's';

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'status' => $report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->info('Laravel Solo Live Reload status');
        $this->table(['Setting', 'Value'], [
            ['Enabled', $report['enabled'] ? 'yes' : 'no'],
            ['Environment', $report['environment']],
            ['Preset', $report['preset']],
            ['Watcher running', $report['watcher_running'] ? 'yes' : 'no'],
            ['Watcher healthy', $report['watcher_healthy'] ? 'yes' : 'no'],
            ['Watcher paused', $report['watcher_paused'] ? 'yes' : 'no'],
            ['Watcher heartbeat', $report['watcher_heartbeat']['timestamp'] ?? 'none'],
            ['Heartbeat age (seconds)', $heartbeatStatus],
            ['Watcher heartbeat stale', $report['watcher_heartbeat_stale'] ? 'yes' : 'no'],
            ['Watcher stale TTL', $report['watcher_stale_ttl_seconds'] . 's'],
            ['Watchable files', (string) $report['watchable_files']],
            ['Poll interval', $report['poll_interval'] . ' ms'],
            ['Scan interval', $report['scan_interval'] . ' ms'],
            ['Debounce', $report['debounce_ms'] . ' ms'],
            ['Reload delay', $report['reload_delay_ms'] . ' ms'],
            ['Route prefix', $report['route_prefix']],
            ['Route token', $report['access_token_set'] ? 'enabled' : 'disabled'],
            ['Safe mode', $report['safe_mode'] ? 'yes' : 'no'],
            ['Loopback-only enforcement', $report['loopback_only'] ? 'yes' : 'no'],
            ['Strict allow-list', $report['strict_allowlist'] ? 'yes' : 'no'],
            ['Allowed client IPs', implode(', ', $report['allowed_client_ips']) ?: 'none'],
            ['Allowed hosts', implode(', ', $report['allowed_hosts']) ?: 'none'],
            ['.env watch', $report['watch_env_file'] ? 'on' : 'off'],
            ['Signal file', $report['signal_file']],
            ['CSS hot reload', $report['css_hot_reload'] ? 'yes' : 'no'],
            ['Error page injection', $report['inject_on_error_pages'] ? 'yes' : 'no'],
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

        $this->line('Ignore patterns:');
        foreach ($report['ignore_patterns'] as $pattern) {
            $this->line(' - ' . $pattern);
        }

        $this->line('Recent changes:');

        if (count($report['last_changes']) === 0) {
            $this->line(' - none');
        } else {
            foreach ($report['last_changes'] as $change) {
                $this->line(' - ' . ($change['changed_type'] ?: 'unknown') . ': ' . ($change['changed_file'] ?: 'none') . ' at ' . ($change['changed_at'] ?: 'never'));
            }
        }
    }
}
