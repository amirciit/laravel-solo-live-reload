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

        return $response->isSuccessful();
    }

    protected function scriptTag()
    {
        $prefix = trim((string) config('live-reload.route_prefix', '__live-reload'), '/');
        $src = url('/' . $prefix . '/client.js');

        return '<script src="' . e($src) . '" data-live-reload defer></script>';
    }
}
