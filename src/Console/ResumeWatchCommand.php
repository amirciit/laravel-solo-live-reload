<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class ResumeWatchCommand extends Command
{
    protected $signature = 'live-reload:resume-watch';

    protected $description = 'Resume the live-reload watcher.';

    protected $signal;

    public function __construct(ReloadSignal $signal)
    {
        parent::__construct();

        $this->signal = $signal;
    }

    public function handle()
    {
        $this->signal->resume();

        $this->info('Live reload watcher resumed.');

        return self::SUCCESS;
    }
}
