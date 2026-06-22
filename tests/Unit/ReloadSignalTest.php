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
        $this->assertFileExists(live_reload_storage_path('.gitignore'));
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

    public function test_reload_signal_records_recent_change_history()
    {
        $signal = app(ReloadSignal::class);

        $first = $signal->write(base_path('resources/views/home.blade.php'), 'updated');
        usleep(2000);
        $second = $signal->write(base_path('routes/web.php'), 'updated');

        $history = $signal->readHistory();

        $this->assertCount(2, $history);
        $this->assertSame($second['version'], $history[0]['version']);
        $this->assertSame($first['version'], $history[1]['version']);
    }

    public function test_clear_removes_reload_files()
    {
        $signal = app(ReloadSignal::class);
        $signal->write(base_path('routes/web.php'));
        $signal->clear();

        $this->assertFileDoesNotExist($signal->path());
        $this->assertFileDoesNotExist($signal->historyPath());
    }
}
