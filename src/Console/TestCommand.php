<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use RuntimeException;

class TestCommand extends Command
{
    protected $signature = 'live-reload:test {--json : Output checks as JSON}';

    protected $description = 'Run a small Laravel Solo Live Reload self-test.';

    protected $signal;

    public function __construct(ReloadSignal $signal)
    {
        parent::__construct();

        $this->signal = $signal;
    }

    public function handle()
    {
        $checks = $this->checks();
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
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $hasFailures ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Laravel Solo Live Reload self-test');

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

    protected function checks()
    {
        $checks = [];

        $checks[] = $this->check(
            'Environment',
            live_reload_enabled($this->laravel) ? 'pass' : 'fail',
            live_reload_enabled($this->laravel)
                ? 'APP_ENV=local and LIVE_RELOAD_ENABLED allow live reload.'
                : 'Set APP_ENV=local and LIVE_RELOAD_ENABLED=true.'
        );

        try {
            $this->signal->ensureStorageDirectory();
            $checks[] = $this->check('Storage', 'pass', 'Live reload storage is writable.');
        } catch (RuntimeException $exception) {
            $checks[] = $this->check('Storage', 'fail', $exception->getMessage());

            return $checks;
        }

        $routesRegistered = Route::has('live-reload.version') && Route::has('live-reload.client') && Route::has('live-reload.status');

        $checks[] = $this->check(
            'Routes',
            $routesRegistered ? 'pass' : 'fail',
            $routesRegistered ? 'Live reload routes are registered.' : 'Live reload routes are not registered.'
        );

        $payload = $this->signal->write(base_path('routes/web.php'), 'self-test');
        $read = $this->signal->read();

        $checks[] = $this->check(
            'Signal write',
            $payload['version'] === $read['version'] ? 'pass' : 'fail',
            $payload['version'] === $read['version']
                ? 'Reload signal can be written and read.'
                : 'Reload signal did not round-trip correctly.'
        );

        $history = $this->signal->readHistory();
        $historyRecorded = count($history) > 0 && $history[0]['version'] === $payload['version'];

        $checks[] = $this->check(
            'Change history',
            $historyRecorded ? 'pass' : 'warn',
            $historyRecorded
                ? 'Recent change history is recording signals.'
                : 'Reload works, but recent change history was not recorded.'
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
