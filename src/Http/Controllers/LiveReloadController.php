<?php

namespace LaravelSolo\LiveReload\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use LaravelSolo\LiveReload\Services\LiveReloadStatus;
use LaravelSolo\LiveReload\Services\ReloadSignal;

class LiveReloadController extends Controller
{
    public function version(Request $request, ReloadSignal $signal, LiveReloadStatus $status)
    {
        if (! live_reload_enabled(app())) {
            return response()->json([
                'enabled' => false,
                'version' => null,
                'changed_file' => null,
                'changed_type' => null,
                'watcher_running' => false,
            ]);
        }

        if (! live_reload_request_is_allowed($request)) {
            abort(404);
        }

        $payload = $signal->read();

        return response()->json([
            'enabled' => true,
            'version' => $payload['version'],
            'changed_file' => $payload['changed_file'],
            'changed_type' => $payload['changed_type'],
            'poll_interval' => (int) config('live-reload.poll_interval', 800),
            'reload_delay_ms' => (int) config('live-reload.reload_delay_ms', 80),
            'watcher_running' => $signal->isWatcherProcessRunning(),
            'watcher_heartbeat' => $signal->readHeartbeat(),
            'watcher_heartbeat_age_seconds' => $signal->heartbeatAgeSeconds(),
            'watcher_heartbeat_stale' => $signal->isHeartbeatStale(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function client(Request $request)
    {
        if (! live_reload_enabled(app()) || ! live_reload_request_is_allowed($request)) {
            abort(404);
        }

        $enabled = live_reload_enabled(app());
        $prefix = live_reload_effective_route_prefix();
        $endpoint = url('/' . $prefix . '/version');
        $endpoint .= live_reload_client_token_query();
        $interval = max(500, (int) config('live-reload.poll_interval', 800));
        $reloadDelay = max(0, (int) config('live-reload.reload_delay_ms', 80));
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
            $reloadDelay,
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
        if (! live_reload_enabled(app())) {
            abort(404);
        }

        if (! live_reload_request_is_allowed($request)) {
            abort(404);
        }

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
            'Reload delay' => $report['reload_delay_ms'] . ' ms',
            'Error page injection' => $report['inject_on_error_pages'] ? 'yes' : 'no',
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

        $html .= '</ul></section></div>';

        $html .= '<section><h2>Recent changes</h2><table><thead><tr><th>File</th><th>Type</th><th>Changed at</th></tr></thead><tbody>';

        if (count($report['last_changes']) === 0) {
            $html .= '<tr><td colspan="3">No changes recorded.</td></tr>';
        } else {
            foreach ($report['last_changes'] as $change) {
                $html .= '<tr><td><code>' . e($change['changed_file'] ?: 'none') . '</code></td><td>' . e($change['changed_type'] ?: 'unknown') . '</td><td>' . e($change['changed_at'] ?: 'never') . '</td></tr>';
            }
        }

        $html .= '</tbody></table></section></main></body></html>';

        return $html;
    }

    protected function clientScript($enabled, $endpoint, $interval, $reloadDelay, $logs, $cssHotReload, $multiTabSync, $desktopNotifications, $overlayEnabled, $overlayPosition, $overlayDuration)
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
    var reloadDelay = {$reloadDelay};
    var showLogs = {$logsJson};
    var cssHotReload = {$cssHotReloadJson};
    var multiTabSync = {$multiTabSyncJson};
    var desktopNotifications = {$desktopNotificationsJson};
    var overlayEnabled = {$overlayEnabledJson};
    var overlayPosition = {$overlayPositionJson};
    var overlayDuration = {$overlayDuration};
    var currentVersion = null;
    var connectionWarned = false;
    var watcherHeartbeatAge = null;
    var tabId = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
    var channel = null;
    var overlay = null;
    var overlayTimer = null;
    var storageKey = '__laravel_solo_live_reload__';
    var lastWatcherRunning = null;
    var lastWatcherHealthy = null;
    var pollDelay = interval;
    var minPollDelay = Math.max(500, interval);
    var maxPollDelay = 10000;
    var reconnectAttempt = 0;
    var scheduler = null;

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
        var reason = changeReason(data);

        if (source !== 'sync') {
            broadcastChange(data);
        }

        if (cssHotReload && isCssChange(data.changed_file)) {
            var count = reloadStylesheets(data.version);

            if (count > 0) {
                log('[LiveReload] CSS updated:', reason);
                showOverlay('CSS updated: ' + reason, 'ok');
                notify('CSS updated: ' + reason);
                return;
            }
        }

        log('[LiveReload] Change detected:', reason);
        log('[LiveReload] Reloading...');
        showOverlay('Reloading: ' + reason, 'reload');
        notify('Reloading: ' + reason);

        window.setTimeout(function () {
            window.location.reload();
        }, reloadDelay);
    }

    function changeReason(data) {
        var file = data && data.changed_file ? data.changed_file : 'change detected';
        var type = data && data.changed_type ? data.changed_type : '';

        return type ? type + ' ' + file : file;
    }

    function handleWatcherState(running) {
        if (typeof running !== 'boolean') {
            return;
        }

        var healthy = !(!running) && watcherHeartbeatAge !== null && watcherHeartbeatAge <= maxPollDelay / 1000;

        if (lastWatcherHealthy === null) {
            if (!healthy) {
                log('[LiveReload] Watcher heartbeat stale or stopped');
                showOverlay('Live Reload watcher issue: ' + describeHeartbeat(watcherHeartbeatAge), 'warn');
            }

            if (running) {
                lastWatcherHealthy = true;
            }

            lastWatcherRunning = running;

            return;
        }

        if (lastWatcherRunning === running && lastWatcherHealthy === healthy) {
            return;
        }

        lastWatcherRunning = running;
        lastWatcherHealthy = healthy;

        if (!running || !healthy) {
            showOverlay('Live Reload watcher issue', 'warn');
            return;
        }

        showOverlay('Live Reload watcher reconnected', 'ok');
    }

    function setPollDelay(success) {
        if (success) {
            pollDelay = minPollDelay;
            reconnectAttempt = 0;
            connectionWarned = false;
            return;
        }

        reconnectAttempt = Math.max(1, reconnectAttempt + 1);
        pollDelay = Math.min(maxPollDelay, minPollDelay * Math.pow(2, reconnectAttempt - 1));
    }

    function describeHeartbeat(age) {
        if (age === null || age === undefined) {
            return 'unknown heartbeat';
        }

        return age + 's since last watcher heartbeat';
    }

    function scheduleNextPoll() {
        scheduler = window.setTimeout(function () {
            checkReload();
        }, pollDelay);
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
            if (scheduler) {
                window.clearTimeout(scheduler);
                scheduler = null;
            }

            var response = await fetch(endpoint, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                warn();
                setPollDelay(false);
                showOverlay('Live Reload endpoint returned ' + response.status + '. Retrying in ' + pollDelay + 'ms.', 'warn');
                scheduleNextPoll();
                return;
            }

            var data = await response.json();
            connectionWarned = false;
            setPollDelay(true);
            watcherHeartbeatAge = data.watcher_heartbeat_age_seconds;

            if (!data.enabled) {
                showOverlay('Live Reload disabled', 'warn');
                scheduleNextPoll();
                return;
            }

            handleWatcherState(data.watcher_running);

            if (currentVersion === null) {
                currentVersion = data.version;
                log('[LiveReload] Connected');
                if (data.watcher_running !== false) {
                    showOverlay('Live Reload connected', 'ok');
                }

                if (data.watcher_heartbeat_stale) {
                    showOverlay('Watcher heartbeat stale (' + describeHeartbeat(watcherHeartbeatAge) + ')', 'warn');
                }

                scheduleNextPoll();
                return;
            }

            handleChange(data, 'poll');
        } catch (error) {
            warn();
            setPollDelay(false);
            showOverlay('Live Reload disconnected (retry in ' + pollDelay + 'ms): ' + error.message, 'warn');
            notify('Live Reload disconnected: ' + error.message);
        }

        scheduleNextPoll();
    }

    setupTabSync();
    checkReload();
    // keep polling from the self-adapting loop below
})();
JS;
    }
}
