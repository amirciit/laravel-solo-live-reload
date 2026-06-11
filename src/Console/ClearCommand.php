<?php

namespace LaravelSolo\LiveReload\Console;

use Illuminate\Console\Command;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class ClearCommand extends Command
{
    protected $signature = 'live-reload:clear';

    protected $description = 'Clear Laravel Solo Live Reload temporary files.';

    protected $signal;

    public function __construct(ReloadSignal $signal)
    {
        parent::__construct();

        $this->signal = $signal;
    }

    public function handle()
    {
        $this->signal->clear();

        $this->info('Laravel Solo Live Reload temporary files cleared.');

        return self::SUCCESS;
    }
}
