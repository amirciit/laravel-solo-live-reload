<?php

namespace LaravelSolo\LiveReload;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LaravelSolo\LiveReload\Console\AboutCommand;
use LaravelSolo\LiveReload\Console\ClearCommand;
use LaravelSolo\LiveReload\Console\DoctorCommand;
use LaravelSolo\LiveReload\Console\InstallCommand;
use LaravelSolo\LiveReload\Console\AllowCommand;
use LaravelSolo\LiveReload\Console\PauseWatchCommand;
use LaravelSolo\LiveReload\Console\DenyCommand;
use LaravelSolo\LiveReload\Console\PresetCommand;
use LaravelSolo\LiveReload\Console\GenerateSecretCommand;
use LaravelSolo\LiveReload\Console\ResumeWatchCommand;
use LaravelSolo\LiveReload\Console\ServeCommand;
use LaravelSolo\LiveReload\Console\SafeModeCommand;
use LaravelSolo\LiveReload\Console\StatusCommand;
use LaravelSolo\LiveReload\Console\WatcherRestartCommand;
use LaravelSolo\LiveReload\Console\WatcherStopCommand;
use LaravelSolo\LiveReload\Console\TestCommand;
use LaravelSolo\LiveReload\Console\WatchCommand;
use LaravelSolo\LiveReload\Http\Middleware\InjectLiveReloadScript;
use LaravelSolo\LiveReload\Services\ConfigResolver;
use LaravelSolo\LiveReload\Services\FileWatcher;
use LaravelSolo\LiveReload\Services\PathFilter;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class LiveReloadServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/live-reload.php', 'live-reload');

        $this->app->singleton(PathFilter::class, function ($app) {
            return new PathFilter(ConfigResolver::resolve($app['config']->get('live-reload', [])));
        });

        $this->app->singleton(ReloadSignal::class, function ($app) {
            return new ReloadSignal($app->make(PathFilter::class));
        });

        $this->app->singleton(FileWatcher::class, function ($app) {
            $config = ConfigResolver::resolve($app['config']->get('live-reload', []));

            return new FileWatcher(
                $app->make(PathFilter::class),
                $app->make(ReloadSignal::class),
                $config
            );
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/live-reload.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/live-reload.php' => config_path('live-reload.php'),
            ], 'live-reload-config');

            $this->commands([
                InstallCommand::class,
                WatchCommand::class,
                ServeCommand::class,
                StatusCommand::class,
                ClearCommand::class,
                DoctorCommand::class,
                PauseWatchCommand::class,
                ResumeWatchCommand::class,
                PresetCommand::class,
                SafeModeCommand::class,
                AboutCommand::class,
                TestCommand::class,
                AllowCommand::class,
                DenyCommand::class,
                GenerateSecretCommand::class,
                WatcherStopCommand::class,
                WatcherRestartCommand::class,
            ]);
        }

        $this->registerMiddleware();
    }

    protected function registerMiddleware()
    {
        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)->pushMiddlewareToGroup('web', InjectLiveReloadScript::class);
        }

        if ($this->app->bound(Kernel::class)) {
            $this->app->make(Kernel::class)->pushMiddleware(InjectLiveReloadScript::class);
        }
    }
}
