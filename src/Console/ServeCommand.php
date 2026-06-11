<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeCommand extends Command
{
    protected $signature = 'live-reload:serve
        {--host=127.0.0.1 : Host passed to php artisan serve}
        {--port=8000 : Port passed to php artisan serve}
        {--open : Open the local development URL in the default browser}
        {--show-server-logs : Show php artisan serve access logs}';

    protected $description = 'Start Laravel artisan serve and the live reload watcher together.';

    public function handle()
    {
        if (! live_reload_enabled($this->laravel)) {
            $this->warn('Laravel Solo Live Reload is disabled for this environment.');

            return self::SUCCESS;
        }

        $host = (string) $this->option('host');
        $port = (string) $this->option('port');
        $url = 'http://' . $host . ':' . $port;
        $artisan = base_path('artisan');

        $server = new Process([PHP_BINARY, $artisan, 'serve', '--host=' . $host, '--port=' . $port], base_path());
        $watcher = new Process([PHP_BINARY, $artisan, 'live-reload:watch', '--url=' . $url], base_path());

        $server->setTimeout(null);
        $watcher->setTimeout(null);

        $this->info('Starting Laravel server and live reload watcher.');
        $this->line('URL: ' . $url);
        $this->line('Press CTRL+C to stop.');

        $showServerLogs = $this->option('show-server-logs') || (bool) config('live-reload.show_server_logs', false);

        $server->start(function ($type, $buffer) use ($showServerLogs) {
            $this->writeServerOutput($buffer, $showServerLogs);
        });

        $watcher->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($this->option('open') || (bool) config('live-reload.auto_open_browser', false)) {
            $this->openBrowser($url);
        }

        while ($server->isRunning() && $watcher->isRunning()) {
            usleep(100000);
        }

        $exitCode = self::SUCCESS;

        if (! $server->isRunning() && $server->getExitCode() !== 0) {
            $exitCode = self::FAILURE;
        }

        if (! $watcher->isRunning() && $watcher->getExitCode() !== 0) {
            $exitCode = self::FAILURE;
        }

        $this->stopProcess($server);
        $this->stopProcess($watcher);

        return $exitCode;
    }

    protected function stopProcess(Process $process)
    {
        if ($process->isRunning()) {
            $process->stop(2);
        }
    }

    protected function writeServerOutput($buffer, $showServerLogs)
    {
        if ($showServerLogs) {
            $this->output->write($buffer);

            return;
        }

        foreach (preg_split('/(\r\n|\n|\r)/', $buffer) as $line) {
            if ($line === '') {
                continue;
            }

            if ($this->isServerAccessLog($line)) {
                continue;
            }

            $this->output->writeln($line);
        }
    }

    protected function isServerAccessLog($line)
    {
        if (preg_match('/^\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+\.+\s+~\s+\d+s\s*$/', $line)) {
            return true;
        }

        return false;
    }

    protected function openBrowser($url)
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $process = new Process(['cmd', '/c', 'start', '', $url], base_path());
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $process = new Process(['open', $url], base_path());
        } else {
            $process = new Process(['xdg-open', $url], base_path());
        }

        try {
            $process->disableOutput();
            $process->start();
            $this->line('Opening browser: ' . $url);
        } catch (\Throwable $exception) {
            $this->warn('Unable to open browser automatically: ' . $exception->getMessage());
        }
    }
}
