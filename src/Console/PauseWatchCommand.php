<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class PauseWatchCommand extends Command
{
    protected $signature = 'live-reload:pause-watch';

    protected $description = 'Pause the live-reload watcher.';

    protected $signal;

    public function __construct(ReloadSignal $signal)
    {
        parent::__construct();

        $this->signal = $signal;
    }

    public function handle()
    {
        $this->signal->pause();

        $this->info('Live reload watcher marked as paused.');
        $this->warn('Use live-reload:resume-watch to resume.');

        return self::SUCCESS;
    }
}
