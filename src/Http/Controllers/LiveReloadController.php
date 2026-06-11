<?php

namespace LaravelSolo\LiveReload\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class LiveReloadController extends Controller
{
    public function version(ReloadSignal $signal)
    {
        if (! live_reload_enabled(app())) {
            return response()->json([
                'enabled' => false,
                'version' => null,
                'changed_file' => null,
                'changed_type' => null,
            ]);
        }

        $payload = $signal->read();

        return response()->json([
            'enabled' => true,
            'version' => $payload['version'],
            'changed_file' => $payload['changed_file'],
            'changed_type' => $payload['changed_type'],
            'poll_interval' => (int) config('live-reload.poll_interval', 800),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function client()
    {
        $enabled = live_reload_enabled(app());
        $prefix = trim((string) config('live-reload.route_prefix', '__live-reload'), '/');
        $endpoint = url('/' . $prefix . '/version');
        $interval = max(500, (int) config('live-reload.poll_interval', 800));
        $logs = (bool) config('live-reload.show_console_logs', true);
        $cssHotReload = (bool) config('live-reload.css_hot_reload', true);
        $multiTabSync = (bool) config('live-reload.multi_tab_sync', true);
        $desktopNotifications = (bool) config('live-reload.desktop_notifications', false);
        $overlayEnabled = (bool) config('live-reload.overlay.enabled', true);
        $overlayPosition = (string) config('live-reload.overlay.position', 'bottom-right');
        $overlayDuration = max(500, (int) config('live-reload.overlay.duration', 2200));

        $script = $this->clientScript(
            $enabled,
            $endpoint,
            $interval,
            $logs,
            $cssHotReload,
            $multiTabSync,
            $desktopNotifications,
            $overlayEnabled,
            $overlayPosition,
            $overlayDuration
        );

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function status(Request $request, LiveReloadStatus $status)
    {
        $report = $status->report(app());

        if ($request->expectsJson() || $request->query('format') === 'json') {
            return response()->json($report)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return response($this->statusHtml($report), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    protected function statusHtml(array $report)
    {
        $rows = [
            'Enabled' => $report['enabled'] ? 'yes' : 'no',
            'Injection enabled' => $report['injection_enabled'] ? 'yes' : 'no',
            'Environment' => $report['environment'],
            'Preset' => $report['preset'],
            'Watcher running' => $report['watcher_running'] ? 'yes' : 'no',
            'Watchable files' => (string) $report['watchable_files'],
            'Last changed file' => $report['last_changed_file'] ?: 'none',
            'Last changed at' => $report['last_changed_at'] ?: 'never',
            'CSS hot reload' => $report['css_hot_reload'] ? 'yes' : 'no',
            'Overlay' => $report['overlay_enabled'] ? 'yes' : 'no',
            'Multi-tab sync' => $report['multi_tab_sync'] ? 'yes' : 'no',
            'Desktop notifications' => $report['desktop_notifications'] ? 'yes' : 'no',
        ];

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Laravel Solo Live Reload Status</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:32px;background:#f8fafc;color:#111827}main{max-width:920px}table{border-collapse:collapse;width:100%;background:#fff}td,th{border:1px solid #e5e7eb;padding:10px;text-align:left}th{background:#f3f4f6}code{background:#eef2ff;padding:2px 5px;border-radius:4px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}@media(max-width:760px){.grid{grid-template-columns:1fr}}</style>';
        $html .= '</head><body><main><h1>Laravel Solo Live Reload Status</h1><table><tbody>';

        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . e($label) . '</th><td>' . e($value) . '</td></tr>';
        }

        $html .= '</tbody></table><div class="grid"><section><h2>Watched paths</h2><ul>';

        foreach ($report['watch_paths'] as $path) {
            $html .= '<li><code>' . e($path) . '</code></li>';
        }

        $html .= '</ul></section><section><h2>Watched extensions</h2><p><code>' . e(implode(', ', $report['watch_extensions'])) . '</code></p>';
        $html .= '<h2>Ignored paths</h2><ul>';

        foreach ($report['ignore_paths'] as $path) {
            $html .= '<li><code>' . e($path) . '</code></li>';
        }

        $html .= '</ul></section></div></main></body></html>';

        return $html;
    }

    protected function clientScript($enabled, $endpoint, $interval, $logs, $cssHotReload, $multiTabSync, $desktopNotifications, $overlayEnabled, $overlayPosition, $overlayDuration)
    {
        $enabledJson = $enabled ? 'true' : 'false';
        $endpointJson = json_encode($endpoint);
        $logsJson = $logs ? 'true' : 'false';
        $cssHotReloadJson = $cssHotReload ? 'true' : 'false';
        $multiTabSyncJson = $multiTabSync ? 'true' : 'false';
        $desktopNotificationsJson = $desktopNotifications ? 'true' : 'false';
        $overlayEnabledJson = $overlayEnabled ? 'true' : 'false';
        $overlayPositionJson = json_encode($overlayPosition);

        return <<<JS
(function () {
    'use strict';

    var enabled = {$enabledJson};
    var endpoint = {$endpointJson};
    var interval = {$interval};
    var showLogs = {$logsJson};
    var cssHotReload = {$cssHotReloadJson};
    var multiTabSync = {$multiTabSyncJson};
    var desktopNotifications = {$desktopNotificationsJson};
    var overlayEnabled = {$overlayEnabledJson};
    var overlayPosition = {$overlayPositionJson};
    var overlayDuration = {$overlayDuration};
    var currentVersion = null;
    var connectionWarned = false;
    var tabId = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    var channel = null;
    var overlay = null;
    var overlayTimer = null;
    var storageKey = '__laravel_solo_live_reload__';

    function log() {
        if (showLogs && window.console && console.log) {
            console.log.apply(console, arguments);
        }
    }

    function warn() {
        if (showLogs && !connectionWarned && window.console && console.warn) {
            connectionWarned = true;
            console.warn('[LiveReload] Connection failed');
        }
    }

    function showOverlay(message, tone) {
        if (!overlayEnabled || !document.body) {
            return;
        }

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.setAttribute('data-live-reload-overlay', 'true');
            overlay.style.position = 'fixed';
            overlay.style.zIndex = '2147483647';
            overlay.style.maxWidth = '360px';
            overlay.style.padding = '10px 12px';
            overlay.style.borderRadius = '8px';
            overlay.style.font = '13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
            overlay.style.boxShadow = '0 10px 30px rgba(15,23,42,.22)';
            overlay.style.color = '#fff';
            overlay.style.pointerEvents = 'none';
            overlay.style.opacity = '0';
            overlay.style.transform = 'translateY(6px)';
            overlay.style.transition = 'opacity .18s ease, transform .18s ease';

            if (overlayPosition.indexOf('top') !== -1) {
                overlay.style.top = '16px';
            } else {
                overlay.style.bottom = '16px';
            }

            if (overlayPosition.indexOf('left') !== -1) {
                overlay.style.left = '16px';
            } else {
                overlay.style.right = '16px';
            }

            document.body.appendChild(overlay);
        }

        overlay.textContent = message;
        overlay.style.background = tone === 'warn' ? '#b45309' : tone === 'reload' ? '#2563eb' : '#047857';
        overlay.style.opacity = '1';
        overlay.style.transform = 'translateY(0)';

        window.clearTimeout(overlayTimer);
        overlayTimer = window.setTimeout(function () {
            if (overlay) {
                overlay.style.opacity = '0';
                overlay.style.transform = 'translateY(6px)';
            }
        }, overlayDuration);
    }

    function notify(message) {
        if (!desktopNotifications || !('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'granted') {
            new Notification('Laravel Solo Live Reload', {
                body: message,
                tag: 'laravel-solo-live-reload'
            });
            return;
        }

        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    function isCssChange(file) {
        return typeof file === 'string' && file.toLowerCase().slice(-4) === '.css';
    }

    function cacheBustUrl(url, version) {
        var hash = '';
        var index = url.indexOf('#');

        if (index !== -1) {
            hash = url.slice(index);
            url = url.slice(0, index);
        }

        var separator = url.indexOf('?') === -1 ? '?' : '&';

        return url + separator + 'live_reload=' + encodeURIComponent(version) + hash;
    }

    function reloadStylesheets(version) {
        var links = document.querySelectorAll('link[rel~="stylesheet"][href]');
        var count = 0;

        Array.prototype.forEach.call(links, function (link) {
            var href = link.getAttribute('href');

            if (!href || href.indexOf('data:') === 0) {
                return;
            }

            link.setAttribute('href', cacheBustUrl(href.replace(/([?&])live_reload=[^&#]*/g, '$1').replace(/[?&]$/, ''), version));
            count++;
        });

        return count;
    }

    function broadcastChange(data) {
        if (!multiTabSync) {
            return;
        }

        var message = {
            type: 'live-reload-change',
            tabId: tabId,
            data: data
        };

        if (channel) {
            channel.postMessage(message);
        } else {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify({
                    time: Date.now(),
                    message: message
                }));
            } catch (error) {
                // Ignore storage failures in private browsing or locked-down contexts.
            }
        }
    }

    function handleChange(data, source) {
        if (!data || !data.version || currentVersion === data.version) {
            return;
        }

        currentVersion = data.version;

        if (source !== 'sync') {
            broadcastChange(data);
        }

        if (cssHotReload && isCssChange(data.changed_file)) {
            var count = reloadStylesheets(data.version);

            if (count > 0) {
                log('[LiveReload] CSS updated:', data.changed_file);
                showOverlay('CSS updated: ' + data.changed_file, 'ok');
                notify('CSS updated: ' + data.changed_file);
                return;
            }
        }

        log('[LiveReload] Change detected:', data.changed_file || 'unknown');
        log('[LiveReload] Reloading...');
        showOverlay('Reloading: ' + (data.changed_file || 'change detected'), 'reload');
        notify('Reloading: ' + (data.changed_file || 'change detected'));

        window.setTimeout(function () {
            window.location.reload();
        }, 80);
    }

    function setupTabSync() {
        if (!multiTabSync) {
            return;
        }

        if ('BroadcastChannel' in window) {
            channel = new BroadcastChannel('laravel-solo-live-reload');
            channel.onmessage = function (event) {
                var message = event.data || {};

                if (message.type === 'live-reload-change' && message.tabId !== tabId) {
                    handleChange(message.data, 'sync');
                }
            };

            return;
        }

        window.addEventListener('storage', function (event) {
            if (event.key !== storageKey || !event.newValue) {
                return;
            }

            try {
                var payload = JSON.parse(event.newValue);
                var message = payload.message || {};

                if (message.type === 'live-reload-change' && message.tabId !== tabId) {
                    handleChange(message.data, 'sync');
                }
            } catch (error) {
                // Ignore invalid storage payloads.
            }
        });
    }

    async function checkReload() {
        if (!enabled) {
            return;
        }

        try {
            var response = await fetch(endpoint, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                warn();
                return;
            }

            var data = await response.json();
            connectionWarned = false;

            if (!data.enabled) {
                return;
            }

            if (currentVersion === null) {
                currentVersion = data.version;
                log('[LiveReload] Connected');
                showOverlay('Live Reload connected', 'ok');
                return;
            }

            handleChange(data, 'poll');
        } catch (error) {
            warn();
            showOverlay('Live Reload disconnected', 'warn');
        }
    }

    setupTabSync();
    checkReload();
    window.setInterval(checkReload, interval);
})();
JS;
    }
}
