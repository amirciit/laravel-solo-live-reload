<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use LaravelSolo\LiveReload\Services\ConfigResolver;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use RuntimeException;

class DoctorCommand extends Command
{
    protected $signature = 'live-reload:doctor {--json : Output checks as JSON}';

    protected $description = 'Diagnose common Laravel Solo Live Reload setup issues.';

    protected $status;
    protected $signal;

    public function __construct(LiveReloadStatus $status, ReloadSignal $signal)
    {
        parent::__construct();

        $this->status = $status;
        $this->signal = $signal;
    }

    public function handle()
    {
        $report = $this->status->report($this->laravel);
        $checks = $this->checks($report);
        $hasFailures = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hasFailures = true;
                break;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $hasFailures,
                'checks' => $checks,
                'report' => $report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $hasFailures ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Laravel Solo Live Reload doctor');

        foreach ($checks as $check) {
            $prefix = $check['status'] === 'pass' ? '[OK]' : ($check['status'] === 'warn' ? '[WARN]' : '[FAIL]');
            $line = $prefix . ' ' . $check['name'] . ': ' . $check['message'];

            if ($check['status'] === 'fail') {
                $this->error($line);
            } elseif ($check['status'] === 'warn') {
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    protected function checks(array $report)
    {
        $checks = [];
        $resolved = ConfigResolver::resolve(config('live-reload', []));
        $allowedClientIps = isset($resolved['allowed_client_ips']) ? (array) $resolved['allowed_client_ips'] : [];
        $allowedHosts = isset($resolved['allowed_hosts']) ? (array) $resolved['allowed_hosts'] : [];
        $hasAllowList = count($allowedClientIps) > 0 || count($allowedHosts) > 0;
        $strictAllowlist = (bool) ($resolved['strict_allowlist'] ?? false);
        $safeMode = (bool) ($resolved['safe_mode'] ?? false);
        $enforceLoopback = (bool) ($resolved['enforce_loopback'] ?? false);
        $token = trim((string) ($resolved['access_token'] ?? ''));
        $routeSecret = trim((string) ($resolved['route_secret'] ?? ''));
        $hasHardening = $safeMode || $enforceLoopback || $token !== '' || $routeSecret !== '' || $hasAllowList;

        $checks[] = $this->check(
            'Environment',
            $this->laravel->environment('local') ? 'pass' : 'fail',
            $this->laravel->environment('local')
                ? 'APP_ENV=local allows live reload.'
                : 'Set APP_ENV=local. Live reload is disabled outside local.'
        );

        $checks[] = $this->check(
            'Package enabled',
            $report['enabled'] ? 'pass' : 'fail',
            $report['enabled']
                ? 'LIVE_RELOAD_ENABLED allows the package to run.'
                : 'Set LIVE_RELOAD_ENABLED=true and APP_ENV=local.'
        );

        $checks[] = $this->check(
            'Middleware injection',
            $report['injection_enabled'] ? 'pass' : 'warn',
            $report['injection_enabled']
                ? 'HTML responses can receive the browser client script.'
                : 'Script injection is disabled or APP_ENV is not local.'
        );

        $checks[] = $this->storageCheck();
        $checks = array_merge($checks, $this->cacheChecks());

        $checks[] = $this->check(
            'Routes',
            Route::has('live-reload.version') && Route::has('live-reload.client') && Route::has('live-reload.status') ? 'pass' : 'fail',
            Route::has('live-reload.version') && Route::has('live-reload.client') && Route::has('live-reload.status')
                ? 'Live reload routes are registered.'
                : 'Live reload routes are not registered.'
        );

        $checks[] = $this->check(
            'Watched paths',
            count($report['watch_paths']) > 0 ? 'pass' : 'fail',
            count($report['watch_paths']) > 0
                ? count($report['watch_paths']) . ' paths are configured.'
                : 'No watch paths are configured.'
        );

        $checks[] = $this->check(
            'Watchable files',
            $report['watchable_files'] > 0 ? 'pass' : 'warn',
            $report['watchable_files'] > 0
                ? $report['watchable_files'] . ' files match the current watcher settings.'
                : 'No files matched the current watch paths and extensions.'
        );

        $checks[] = $this->check(
            'Watcher process',
            $report['watcher_running'] ? 'pass' : 'warn',
            $report['watcher_running']
                ? 'The watcher process is currently running.'
                : 'Start it with php artisan live-reload:watch or live-reload:serve.'
        );

        $checks[] = $this->check(
            'Route hardening',
            $hasHardening ? 'pass' : 'fail',
            $hasHardening
                ? 'At least one hardening control is enabled for endpoint access.'
                : 'Enable safe mode or loopback enforcement, or set token/route secret or allow-list.'
        );

        $checks[] = $this->check(
            'Strict allow-list mode',
            $strictAllowlist ? 'pass' : 'warn',
            $strictAllowlist
                ? 'Strict allow-list mode is enabled.'
                : 'Strict allow-list mode is not enabled.'
        );

        if (! $hasHardening) {
            $checks[] = $this->check(
                'Insecure endpoint posture',
                'fail',
                'safe_mode and enforce_loopback are disabled with no route secret/token/allow-list.'
            );
        }

        $checks[] = $this->check(
            'CSS hot reload',
            $report['css_hot_reload'] ? 'pass' : 'warn',
            $report['css_hot_reload']
                ? 'CSS changes can refresh stylesheets without a full page reload.'
                : 'CSS hot reload is disabled.'
        );

        return $checks;
    }

    protected function storageCheck()
    {
        try {
            $this->signal->ensureStorageDirectory();

            return $this->check('Storage', 'pass', 'Live reload storage is writable.');
        } catch (RuntimeException $exception) {
            return $this->check('Storage', 'fail', $exception->getMessage());
        }
    }

    protected function cacheChecks()
    {
        $checks = [];
        $configCache = base_path('bootstrap/cache/config.php');
        $routeCaches = glob(base_path('bootstrap/cache/routes*.php')) ?: [];

        $checks[] = $this->check(
            'Config cache',
            is_file($configCache) ? 'warn' : 'pass',
            is_file($configCache)
                ? 'Config cache exists. .env or config/live-reload.php changes may need php artisan optimize:clear.'
                : 'No config cache file was found.'
        );

        $checks[] = $this->check(
            'Route cache',
            count($routeCaches) > 0 ? 'warn' : 'pass',
            count($routeCaches) > 0
                ? 'Route cache exists. Route changes may need php artisan optimize:clear.'
                : 'No route cache file was found.'
        );

        return $checks;
    }

    protected function check($name, $status, $message)
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }
}
