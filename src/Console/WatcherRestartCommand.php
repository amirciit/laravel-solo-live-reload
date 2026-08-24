<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;

class WatcherRestartCommand extends Command
{
    protected $signature = 'live-reload:watcher-restart
        {--url=http://127.0.0.1:8000 : URL shown by the watch command}
        {--force : Force stop stale or active watcher before restart}';

    protected $description = 'Restart the live-reload watcher process.';

    public function handle()
    {
        $this->call('live-reload:watcher-stop', [
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Restarting live reload watcher...');

        return $this->call('live-reload:watch', [
            '--url' => (string) $this->option('url'),
        ]);
    }
}
