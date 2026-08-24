<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class WatcherStopCommand extends Command
{
    protected $signature = 'live-reload:watcher-stop
        {--force : Force stop even if watcher heartbeat is fresh}';

    protected $description = 'Stop the live-reload watcher process.';

    protected $signal;

    public function __construct(ReloadSignal $signal)
    {
        parent::__construct();

        $this->signal = $signal;
    }

    public function handle()
    {
        if ($this->signal->stopWatcher((bool) $this->option('force'))) {
            $this->info('Live reload watcher stopped.');

            return self::SUCCESS;
        }

        $this->warn('Watcher is running and did not exceed stale TTL. Use --force to stop it.');

        return self::FAILURE;
    }
}
