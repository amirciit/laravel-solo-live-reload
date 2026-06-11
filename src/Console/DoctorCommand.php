<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
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

        $checks[] = $this->check(
            'Environment',
            $this->laravel->environment('production') ? 'fail' : 'pass',
            $this->laravel->environment('production')
                ? 'APP_ENV is production, so live reload is disabled.'
                : 'APP_ENV allows local live reload.'
        );

        $checks[] = $this->check(
            'Package enabled',
            $report['enabled'] ? 'pass' : 'fail',
            $report['enabled']
                ? 'LIVE_RELOAD_ENABLED allows the package to run.'
                : 'Set LIVE_RELOAD_ENABLED=true and avoid production environment.'
        );

        $checks[] = $this->check(
            'Middleware injection',
            $report['injection_enabled'] ? 'pass' : 'warn',
            $report['injection_enabled']
                ? 'HTML responses can receive the browser client script.'
                : 'Script injection is disabled or the environment is not local/development/testing.'
        );

        $checks[] = $this->storageCheck();

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

    protected function check($name, $status, $message)
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }
}
