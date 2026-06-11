<?php

namespace LaravelSolo\LiveReload\Tests\Unit;

use LaravelSolo\LiveReload\LiveReloadServiceProvider;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use Orchestra\Testbench\TestCase;

class ReloadSignalTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LiveReloadServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'local';
        $app['config']->set('live-reload.storage_path', storage_path('framework/live-reload-tests'));
    }

    protected function tearDown(): void
    {
        app(ReloadSignal::class)->clear();

        parent::tearDown();
    }

    public function test_reload_json_file_is_created()
    {
        $signal = app(ReloadSignal::class);
        $payload = $signal->write(base_path('resources/views/home.blade.php'), 'updated');

        $this->assertFileExists($signal->path());
        $this->assertSame('resources/views/home.blade.php', $payload['changed_file']);
        $this->assertSame('updated', $payload['changed_type']);
    }

    public function test_reload_signal_updates_version()
    {
        $signal = app(ReloadSignal::class);

        $first = $signal->write(base_path('resources/views/home.blade.php'));
        usleep(2000);
        $second = $signal->write(base_path('routes/web.php'));

        $this->assertNotSame($first['version'], $second['version']);
        $this->assertSame('routes/web.php', $signal->read()['changed_file']);
    }

    public function test_clear_removes_reload_files()
    {
        $signal = app(ReloadSignal::class);
        $signal->write(base_path('routes/web.php'));
        $signal->clear();

        $this->assertFileDoesNotExist($signal->path());
    }
}
