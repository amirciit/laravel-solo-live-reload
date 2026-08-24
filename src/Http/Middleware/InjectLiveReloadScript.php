<?php

namespace LaravelSolo\LiveReload\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InjectLiveReloadScript
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || stripos($content, 'data-live-reload') !== false) {
            return $response;
        }

        $script = $this->scriptTag();

        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body\s*>/i', $script . PHP_EOL . '</body>', $content, 1);
        } else {
            $content .= PHP_EOL . $script;
        }

        $response->setContent($content);
        $response->headers->remove('Content-Length');

        return $response;
    }

    protected function shouldInject($request, $response)
    {
        if (! live_reload_injection_enabled(app())) {
            return false;
        }

        if (! live_reload_request_is_allowed($request)) {
            return false;
        }

        if (! $response instanceof Response) {
            return false;
        }

        if ($response instanceof JsonResponse || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return false;
        }

        if ($response->headers->has('Content-Disposition')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (stripos($contentType, 'text/html') === false) {
            return false;
        }

        if ($response->isRedirection() || $response->isEmpty()) {
            return false;
        }

        if (! $response->isSuccessful() && ! (bool) config('live-reload.inject_on_error_pages', true)) {
            return false;
        }

        return true;
    }

    protected function scriptTag()
    {
        $prefix = live_reload_effective_route_prefix();
        $src = url('/' . $prefix . '/client.js');
        $src .= live_reload_client_token_query();

        return '<script src="' . e($src) . '" data-live-reload defer></script>';
    }
}
