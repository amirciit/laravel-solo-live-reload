<?php

namespace LaravelSolo\LiveReload\Tests\Unit;

use LaravelSolo\LiveReload\LiveReloadServiceProvider;
use LaravelSolo\LiveReload\Services\FileWatcher;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use Orchestra\Testbench\TestCase;

class FileWatcherTest extends TestCase
{
    protected $fixturePath;

    protected function getPackageProviders($app)
    {
        return [LiveReloadServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->fixturePath = storage_path('framework/live-reload-fixtures');

        $app['env'] = 'local';
        $app['config']->set('live-reload.storage_path', storage_path('framework/live-reload-tests'));
        $app['config']->set('live-reload.watch_paths', [$this->fixturePath]);
        $app['config']->set('live-reload.watch_extensions', ['php', 'blade.php', 'css', 'js', 'json', 'env']);
        $app['config']->set('live-reload.ignore_paths', [$this->fixturePath . DIRECTORY_SEPARATOR . 'ignored']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeDirectory($this->fixturePath);
        mkdir($this->fixturePath, 0775, true);
    }

    protected function tearDown(): void
    {
        app(ReloadSignal::class)->clear();
        $this->removeDirectory($this->fixturePath);

        parent::tearDown();
    }

    public function test_file_watcher_detects_created_updated_and_deleted_files()
    {
        $watcher = app(FileWatcher::class);
        $file = $this->fixturePath . DIRECTORY_SEPARATOR . 'home.blade.php';

        $before = $watcher->snapshot();
        file_put_contents($file, 'first');
        clearstatcache(true, $file);
        $created = $watcher->snapshot();

        $this->assertSame([
            [
                'type' => 'created',
                'path' => 'storage/framework/live-reload-fixtures/home.blade.php',
            ],
        ], $watcher->detectChanges($before, $created));

        touch($file, time() + 2);
        file_put_contents($file, 'second version');
        clearstatcache(true, $file);
        $updated = $watcher->snapshot();
        $updatedChanges = $watcher->detectChanges($created, $updated);

        $this->assertSame('updated', $updatedChanges[0]['type']);

        unlink($file);
        clearstatcache(true, $file);
        $deleted = $watcher->snapshot();
        $deletedChanges = $watcher->detectChanges($updated, $deleted);

        $this->assertSame('deleted', $deletedChanges[0]['type']);
    }

    public function test_file_watcher_ignores_configured_folders()
    {
        $ignored = $this->fixturePath . DIRECTORY_SEPARATOR . 'ignored';
        mkdir($ignored, 0775, true);
        file_put_contents($ignored . DIRECTORY_SEPARATOR . 'hidden.php', '<?php echo "hidden";');

        $snapshot = app(FileWatcher::class)->snapshot();

        $this->assertArrayNotHasKey('storage/framework/live-reload-fixtures/ignored/hidden.php', $snapshot);
    }

    public function test_file_watcher_ignores_unwatched_extensions()
    {
        file_put_contents($this->fixturePath . DIRECTORY_SEPARATOR . 'notes.txt', 'ignore me');

        $snapshot = app(FileWatcher::class)->snapshot();

        $this->assertSame([], $snapshot);
    }

    protected function removeDirectory($directory)
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);

        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
