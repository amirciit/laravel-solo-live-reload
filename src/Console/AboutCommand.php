<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;

class AboutCommand extends Command
{
    protected $signature = 'live-reload:about {--json : Output package information as JSON}';

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
        $host = (string) parse_url(config('app.url'), PHP_URL_HOST);
        $isLoopback = live_reload_is_loopback_address($host);
        $autoOpenSafe = $isLoopback
            || (! ($report['safe_mode'] || $report['loopback_only']) && ($report['access_token_set'] || $report['route_secret_set']));

        $endpointChecks = [
            'Version endpoint' => $this->checkEndpoint($report['route_version_url']),
            'Client endpoint' => $this->checkEndpoint($report['route_client_url']),
            'Status endpoint' => $this->checkEndpoint($report['route_status_url']),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'package' => 'laravel-solo/live-reload',
                'description' => 'PHP-only live reload for Laravel local development',
                'environment' => $report['environment'],
                'enabled' => $report['enabled'],
                'preset' => $report['preset'],
                'route_prefix' => $report['route_prefix'],
                'route_secret_set' => $report['route_secret_set'],
                'access_token_set' => $report['access_token_set'],
                'strict_allowlist' => $report['strict_allowlist'] ?? false,
                'safe_mode' => $report['safe_mode'],
                'loopback_only' => $report['loopback_only'],
                'allowed_client_ips' => $report['allowed_client_ips'],
                'allowed_hosts' => $report['allowed_hosts'],
                'auto_open_safe_for_app_host' => $autoOpenSafe,
                'route_version_url' => $report['route_version_url'],
                'route_client_url' => $report['route_client_url'],
                'route_status_url' => $report['route_status_url'],
                'watcher_running' => $report['watcher_running'],
                'watcher_healthy' => $report['watcher_healthy'],
                'watcher_heartbeat_age_seconds' => $report['watcher_heartbeat_age_seconds'],
                'config_path' => 'config/live-reload.php',
                'storage_path' => $report['storage_path'],
                'endpoint_checks' => $endpointChecks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Laravel Solo Live Reload');
        $this->table(['Item', 'Value'], [
            ['Package', 'laravel-solo/live-reload'],
            ['Description', 'PHP-only live reload for Laravel local development'],
            ['Environment', $report['environment']],
            ['Enabled', $report['enabled'] ? 'yes' : 'no'],
            ['Preset', $report['preset']],
            ['Route prefix', $report['route_prefix']],
            ['Status URL', $report['route_status_url']],
            ['Version URL', $report['route_version_url']],
            ['Client URL', $report['route_client_url']],
            ['Safe mode', $report['safe_mode'] ? 'yes' : 'no'],
            ['Loopback-only enforcement', $report['loopback_only'] ? 'yes' : 'no'],
            ['Strict allow-list', $report['strict_allowlist'] ? 'yes' : 'no'],
            ['Access token', $report['access_token_set'] ? 'set' : 'not set'],
            ['Route secret', $report['route_secret_set'] ? 'set' : 'not set'],
            ['Allowed IPs', implode(', ', $report['allowed_client_ips']) ?: 'none'],
            ['Allowed hosts', implode(', ', $report['allowed_hosts']) ?: 'none'],
            ['Auto-open safe for app host', $autoOpenSafe ? 'yes' : 'no'],
            ['Version endpoint reachable', $endpointChecks['Version endpoint']],
            ['Client endpoint reachable', $endpointChecks['Client endpoint']],
            ['Status endpoint reachable', $endpointChecks['Status endpoint']],
            ['Config path', 'config/live-reload.php'],
            ['Storage path', $report['storage_path']],
        ]);

        return self::SUCCESS;
    }

    protected function checkEndpoint($url)
    {
        if ($url === '') {
            return 'disabled';
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1.0,
                'ignore_errors' => true,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);

        if ($contents === false) {
            return 'not reachable';
        }

        $status = 'not reachable';
        if (is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
                $status = $matches[1] === '200' ? 'reachable' : ('status ' . $matches[1]);
            }
        }

        return $status;
    }
}
