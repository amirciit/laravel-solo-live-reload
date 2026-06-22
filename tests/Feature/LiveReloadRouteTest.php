<?php

namespace LaravelSolo\LiveReload\Tests\Feature;

use LaravelSolo\LiveReload\LiveReloadServiceProvider;
use LaravelSolo\LiveReload\Services\ReloadSignal;
use Orchestra\Testbench\TestCase;

class LiveReloadRouteTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LiveReloadServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'local';
        $app['config']->set('live-reload.enabled', true);
        $app['config']->set('live-reload.storage_path', storage_path('framework/live-reload-tests'));
    }

    protected function tearDown(): void
    {
        app(ReloadSignal::class)->clear();

        parent::tearDown();
    }

    public function test_config_is_loaded()
    {
        $this->assertSame('__live-reload', config('live-reload.route_prefix'));
        $this->assertContains('blade.php', config('live-reload.watch_extensions'));
    }

    public function test_version_route_returns_current_reload_payload()
    {
        app(ReloadSignal::class)->write(base_path('resources/views/home.blade.php'));

        $this->get('/__live-reload/version')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'changed_file' => 'resources/views/home.blade.php',
            ])
            ->assertJsonStructure([
                'enabled',
                'version',
                'changed_file',
                'changed_type',
                'poll_interval',
                'reload_delay_ms',
                'watcher_running',
            ]);
    }

    public function test_version_route_returns_disabled_payload_outside_local()
    {
        $this->app['env'] = 'development';

        $this->get('/__live-reload/version')
            ->assertOk()
            ->assertJson([
                'enabled' => false,
                'version' => null,
                'changed_file' => null,
                'changed_type' => null,
                'watcher_running' => false,
            ]);
    }

    public function test_client_script_route_is_served_without_cache()
    {
        $response = $this->get('/__live-reload/client.js');

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('[LiveReload] Connected', $response->getContent());
        $this->assertStringContainsString('reloadStylesheets', $response->getContent());
        $this->assertStringContainsString('Live Reload watcher stopped', $response->getContent());
        $this->assertStringContainsString('reloadDelay', $response->getContent());
        $this->assertStringContainsString('BroadcastChannel', $response->getContent());
        $this->assertStringContainsString('Notification', $response->getContent());
        $this->assertStringContainsString('data-live-reload-overlay', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_client_script_route_is_not_served_outside_local()
    {
        $this->app['env'] = 'production';

        $this->get('/__live-reload/client.js')->assertNotFound();
    }

    public function test_status_route_returns_json_report()
    {
        $this->getJson('/__live-reload/status')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'environment' => 'local',
                'preset' => 'laravel',
            ])
            ->assertJsonStructure([
                'enabled',
                'injection_enabled',
                'watcher_running',
                'watchable_files',
                'watch_paths',
                'watch_extensions',
                'ignore_paths',
                'last_changes',
                'reload_delay_ms',
                'inject_on_error_pages',
                'desktop_notifications',
            ]);
    }

    public function test_status_route_returns_html_dashboard()
    {
        $response = $this->get('/__live-reload/status');

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Laravel Solo Live Reload Status', $response->getContent());
    }

    public function test_status_route_is_not_served_outside_local()
    {
        $this->app['env'] = 'production';

        $this->get('/__live-reload/status')->assertNotFound();
    }

    public function test_status_command_works()
    {
        $this->artisan('live-reload:status')
            ->expectsOutput('Laravel Solo Live Reload status')
            ->assertExitCode(0);
    }

    public function test_doctor_command_works()
    {
        $this->artisan('live-reload:doctor')
            ->expectsOutput('Laravel Solo Live Reload doctor')
            ->assertExitCode(0);
    }

    public function test_self_test_command_works()
    {
        $this->artisan('live-reload:test')
            ->expectsOutput('Laravel Solo Live Reload self-test')
            ->assertExitCode(0);
    }

    public function test_about_command_works()
    {
        $this->artisan('live-reload:about')
            ->expectsOutput('Laravel Solo Live Reload')
            ->assertExitCode(0);
    }
}
