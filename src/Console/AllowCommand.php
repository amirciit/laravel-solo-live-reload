<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ConfigResolver;

class AllowCommand extends Command
{
    protected $signature = 'live-reload:allow
        {value? : Host or IP address to allow}
        {--scope=both : Scope to mutate (ip, host, both)}
        {--clear : Clear selected allow-list entries}';

    protected $description = 'Add entries to live-reload allow-lists.';

    public function handle()
    {
        $value = (string) $this->argument('value');
        $scope = strtolower((string) $this->option('scope'));
        $clear = (bool) $this->option('clear');

        if (! in_array($scope, ['ip', 'host', 'both'], true)) {
            $this->error('Invalid scope. Use --scope=ip, --scope=host, or --scope=both.');

            return self::FAILURE;
        }

        $config = ConfigResolver::resolve((array) config('live-reload', []));
        $ips = isset($config['allowed_client_ips']) ? (array) $config['allowed_client_ips'] : [];
        $hosts = isset($config['allowed_hosts']) ? (array) $config['allowed_hosts'] : [];

        if ($clear) {
            if ($scope === 'ip' || $scope === 'both') {
                $ips = [];
            }

            if ($scope === 'host' || $scope === 'both') {
                $hosts = [];
            }

            ConfigResolver::setRuntimeOverrides([
                'allowed_client_ips' => $ips,
                'allowed_hosts' => $hosts,
            ]);

            $this->info('Allowed list cleared for scope: ' . $scope);

            return self::SUCCESS;
        }

        if ($value === '') {
            $this->info('Allowed IPs: ' . (count($ips) ? implode(', ', $ips) : 'none'));
            $this->info('Allowed hosts: ' . (count($hosts) ? implode(', ', $hosts) : 'none'));

            return self::SUCCESS;
        }

        if ($scope === 'both') {
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                $scope = 'ip';
            } else {
                $scope = 'host';
            }
        }

        if ($scope === 'ip') {
            if (! filter_var($value, FILTER_VALIDATE_IP)) {
                $this->error('Value is not a valid IP.');

                return self::FAILURE;
            }

            if (! in_array($value, $ips, true)) {
                $ips[] = $value;
                ConfigResolver::setRuntimeOverrides(['allowed_client_ips' => $ips]);
            }

            $this->info('Allowed IP added: ' . $value);

            return self::SUCCESS;
        }

        if (! $this->isValidHost($value)) {
            $this->error('Value is not a valid host.');

            return self::FAILURE;
        }

        if (! in_array($value, $hosts, true)) {
            $hosts[] = $value;
            ConfigResolver::setRuntimeOverrides(['allowed_hosts' => $hosts]);
        }

        $this->info('Allowed host added: ' . $value);

        return self::SUCCESS;
    }

    protected function isValidHost($host)
    {
        $host = (string) $host;

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }
}
