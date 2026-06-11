<?php

namespace LaravelSolo\LiveReload\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSolo\LiveReload\Http\Middleware\InjectLiveReloadScript;
use LaravelSolo\LiveReload\LiveReloadServiceProvider;
use Orchestra\Testbench\TestCase;

class MiddlewareInjectionTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LiveReloadServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'local';
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('live-reload.enabled', true);
        $app['config']->set('live-reload.inject_script', true);
    }

    protected function defineRoutes($router)
    {
        Route::middleware(InjectLiveReloadScript::class)->get('/html-response', function () {
            return response('<!doctype html><html><body><h1>Hello</h1></body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        });

        Route::middleware(InjectLiveReloadScript::class)->get('/html-no-body', function () {
            return response('<main>Hello</main>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        });

        Route::middleware(InjectLiveReloadScript::class)->get('/json-response', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware(InjectLiveReloadScript::class)->get('/api/html-response', function () {
            return response('<html><body>API</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        });
    }

    public function test_middleware_injects_script_into_html_before_body_close()
    {
        $content = $this->get('/html-response')->getContent();

        $this->assertStringContainsString('data-live-reload', $content);
        $this->assertStringContainsString('/__live-reload/client.js', $content);
        $this->assertLessThan(stripos($content, '</body>'), stripos($content, 'data-live-reload'));
    }

    public function test_middleware_appends_script_when_body_tag_is_missing()
    {
        $content = $this->get('/html-no-body')->getContent();

        $this->assertStringEndsWith('</script>', trim($content));
        $this->assertStringContainsString('data-live-reload', $content);
    }

    public function test_middleware_does_not_inject_into_json()
    {
        $content = $this->get('/json-response')->getContent();

        $this->assertSame('{"ok":true}', $content);
        $this->assertStringNotContainsString('data-live-reload', $content);
    }

    public function test_middleware_does_not_inject_into_api_paths()
    {
        $content = $this->get('/api/html-response')->getContent();

        $this->assertStringNotContainsString('data-live-reload', $content);
    }

    public function test_middleware_does_not_run_in_production()
    {
        $this->app['env'] = 'production';

        $content = $this->get('/html-response')->getContent();

        $this->assertStringNotContainsString('data-live-reload', $content);
    }
}
