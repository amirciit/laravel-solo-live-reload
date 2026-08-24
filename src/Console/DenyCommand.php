<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ConfigResolver;

class DenyCommand extends Command
{
    protected $signature = 'live-reload:deny
        {value? : Host or IP address to remove from allow-lists}
        {--scope=both : Scope to mutate (ip, host, both)}';

    protected $description = 'Remove entries from live-reload allow-lists.';

    public function handle()
    {
        $value = (string) $this->argument('value');
        $scope = strtolower((string) $this->option('scope'));

        if (! in_array($scope, ['ip', 'host', 'both'], true)) {
            $this->error('Invalid scope. Use --scope=ip, --scope=host, or --scope=both.');

            return self::FAILURE;
        }

        $config = ConfigResolver::resolve((array) config('live-reload', []));
        $ips = isset($config['allowed_client_ips']) ? (array) $config['allowed_client_ips'] : [];
        $hosts = isset($config['allowed_hosts']) ? (array) $config['allowed_hosts'] : [];

        if ($value === '') {
            $this->info('Allowed IPs: ' . (count($ips) ? implode(', ', $ips) : 'none'));
            $this->info('Allowed hosts: ' . (count($hosts) ? implode(', ', $hosts) : 'none'));

            return self::SUCCESS;
        }

        if ($scope === 'both') {
            $scope = filter_var($value, FILTER_VALIDATE_IP) ? 'ip' : 'host';
        }

        if ($scope === 'ip') {
            $ips = array_values(array_filter($ips, function ($candidate) use ($value) {
                return $candidate !== $value;
            }));
            ConfigResolver::setRuntimeOverrides(['allowed_client_ips' => $ips]);
            $this->info('Removed from IP allow-list: ' . $value);

            return self::SUCCESS;
        }

        $hosts = array_values(array_filter($hosts, function ($candidate) use ($value) {
            return $candidate !== $value;
        }));
        ConfigResolver::setRuntimeOverrides(['allowed_hosts' => $hosts]);
        $this->info('Removed from host allow-list: ' . $value);

        return self::SUCCESS;
    }
}
