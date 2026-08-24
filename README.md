# Laravel Solo Live Reload

A PHP-only live reload package for Laravel local development.

Laravel Solo Live Reload watches your Laravel files, injects a browser client into local HTML responses, and reloads the browser when files change. It does not require Node.js, npm, Vite, Webpack, Laravel Mix, BrowserSync, socket.io, or any frontend build tool.

## Why This Package?

Laravel already has excellent frontend tooling, but many projects do not need a full JavaScript build pipeline just to refresh the browser after editing Blade, PHP, CSS, or JavaScript files.

This package is designed for simple Laravel development workflows:

- PHP-only live reload
- No Node.js required
- No npm required
- No Vite required
- No BrowserSync required
- Works with `php artisan serve`
- Safe for local development only
- Runs only when `APP_ENV=local`

## Features

- Auto browser reload on watched file changes
- CSS-only hot reload for `.css` files
- Browser overlay for connection and reload status
- Watcher health warning in the browser when the watcher stops
- Multi-tab sync using `BroadcastChannel` with `localStorage` fallback
- Optional watcher pause and resume commands
- Watcher restart/stop commands with stale-process protection
- Optional desktop notifications
- Local status dashboard
- Recent change history in status output
- Watcher heartbeat indicators in terminal and status output
- Setup diagnostics with `live-reload:doctor`
- Pipeline self-test with `live-reload:test`
- Package summary with `live-reload:about`
- Runtime hardening tooling (`safe-mode`, `token:generate`, `allow`, `deny`)
- Optional browser auto-open with `--open`
- Clean terminal output by default
- Optional raw server logs with `--show-server-logs`
- Configurable watch paths, extensions, ignored paths, presets
- Optional route hardening with token, host/IP allow-lists, route secret, and loopback enforcement
- `.gitignore` auto-ignore for watcher patterns and user-defined `ignore_patterns`

## Requirements

- PHP `^7.3` or `^8.0`
- Laravel 8 or newer
- Composer

## Installation

Install the package as a development dependency:

```bash
composer require laravel-solo/live-reload --dev
```

Publish the configuration file:

```bash
php artisan live-reload:install
```

This creates:

```txt
config/live-reload.php
```

If you recently installed or updated the package, clear Laravel caches:

```bash
php artisan optimize:clear
```

## Quick Start

Start Laravel and the live reload watcher together:

```bash
php artisan live-reload:serve --host=127.0.0.1 --port=8000
```

Open:

```txt
http://127.0.0.1:8000
```

The browser reloads automatically when a watched file changes.

## Common Commands

### Start Server And Watcher

```bash
php artisan live-reload:serve --host=127.0.0.1 --port=8000
```

Runs `php artisan serve` and `php artisan live-reload:watch` together.

### Start And Open Browser

```bash
php artisan live-reload:serve --host=127.0.0.1 --port=8000 --open
```

Starts the server, starts the watcher, and opens your browser automatically.

### Show Raw Server Logs

```bash
php artisan live-reload:serve --host=127.0.0.1 --port=8000 --show-server-logs
```

By default, raw `php artisan serve` access logs are hidden because the live reload client polls frequently. Use this option only when debugging requests.

### Run Watcher Separately

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
php artisan live-reload:watch
```

### Pause / Resume Watcher

```bash
php artisan live-reload:pause-watch
php artisan live-reload:resume-watch
```

### Watcher Stop / Restart

```bash
php artisan live-reload:watcher-stop
php artisan live-reload:watcher-stop --force
php artisan live-reload:watcher-restart --url=http://127.0.0.1:8000
```

`watcher-stop` stops the running watcher process when stale; use `--force` to stop immediately.
`watcher-restart` combines stop + watch and is useful in terminal workflows.

### Per-Project Presets

```bash
php artisan live-reload:preset
```

Lists available presets and the current active preset.

```bash
php artisan live-reload:preset blade-only
```

Applies a preset override locally for this project.

```bash
php artisan live-reload:preset --clear
```

Clears the local preset override.

### Safe Mode

```bash
php artisan live-reload:safe-mode
```

Enables safe-mode defaults for non-loopback access:

- Keeps loopback/allow-list enforcement on
- Generates `route_secret` if missing
- Stores overrides locally

```bash
php artisan live-reload:safe-mode --off
```

Disables safe-mode and loopback-only restrictions for this project runtime.

```bash
php artisan live-reload:allow 127.0.0.1 --scope=ip
php artisan live-reload:allow example.dev --scope=host
php artisan live-reload:allow --clear
php artisan live-reload:deny 127.0.0.1

php artisan live-reload:token:generate
php artisan live-reload:token:generate --route
php artisan live-reload:token:generate --route --show-secret
```

Use `allow`/`deny` to control endpoint access without restarting Laravel. Use `token:generate` to rotate access tokens or route secrets for private tunnels.

### Status

```bash
php artisan live-reload:status
```

Shows whether live reload is enabled, watcher state (running/paused), heartbeat age, watch config, and last changes.

### Doctor

```bash
php artisan live-reload:doctor
```

Checks common setup problems:

- Environment
- Package enabled state
- Middleware injection
- Storage writability
- Route registration
- Watched paths
- Watchable files
- Watcher process
- CSS hot reload
- Laravel config and route cache warnings

JSON output:

```bash
php artisan live-reload:doctor --json
```

### About

```bash
php artisan live-reload:about
```

Shows package metadata, endpoints, route secret/token status, runtime guard flags, endpoint reachability checks, and auto-open safety.

### Self-Test

```bash
php artisan live-reload:test
```

Checks local environment gating, storage/writes, route registration, reload signal integrity, history recording, loopback behavior, and hardening status.

JSON output:

```bash
php artisan live-reload:test --json
```

### Clear Temporary Files

```bash
php artisan live-reload:clear
```

Deletes temporary files from:

```txt
storage/framework/live-reload
```

Use this if the watcher says it is already running after a terminal was closed unexpectedly.

## Browser Status Dashboard

Open:

```txt
http://127.0.0.1:8000/__live-reload/status
```

JSON status:

```txt
http://127.0.0.1:8000/__live-reload/status?format=json
```

## Configuration

Published config:

```txt
config/live-reload.php
```

Useful `.env` options:

```dotenv
LIVE_RELOAD_ENABLED=true
LIVE_RELOAD_PRESET=laravel
LIVE_RELOAD_POLL_INTERVAL=800
LIVE_RELOAD_SCAN_INTERVAL=500
LIVE_RELOAD_DEBOUNCE_MS=300
LIVE_RELOAD_RELOAD_DELAY_MS=80
LIVE_RELOAD_SAFE_MODE=1
LIVE_RELOAD_ENFORCE_LOOPBACK=1
LIVE_RELOAD_STRICT_ALLOWLIST=0
LIVE_RELOAD_ALLOWED_CLIENT_IPS=
LIVE_RELOAD_ALLOWED_HOSTS=
LIVE_RELOAD_WATCHER_STALE_TTL_SECONDS=75
LIVE_RELOAD_ACCESS_TOKEN=
LIVE_RELOAD_ROUTE_SECRET=
LIVE_RELOAD_WATCH_ENV_FILE=0
LIVE_RELOAD_INJECT_ON_ERROR_PAGES=true
LIVE_RELOAD_STATUS_HISTORY_LIMIT=10
LIVE_RELOAD_SHOW_SERVER_LOGS=false
LIVE_RELOAD_CSS_HOT_RELOAD=true
LIVE_RELOAD_MULTI_TAB_SYNC=true
LIVE_RELOAD_AUTO_OPEN_BROWSER=false
LIVE_RELOAD_DESKTOP_NOTIFICATIONS=false
LIVE_RELOAD_OVERLAY=true
LIVE_RELOAD_OVERLAY_POSITION=bottom-right
LIVE_RELOAD_OVERLAY_DURATION=2200
```

Optional hardening options:

```dotenv
LIVE_RELOAD_ALLOWED_CLIENT_IPS=127.0.0.1,::1,192.0.2.10
LIVE_RELOAD_ALLOWED_HOSTS=127.0.0.1,localhost
LIVE_RELOAD_ACCESS_TOKEN=super-secret-token
LIVE_RELOAD_ROUTE_SECRET=hidden-route-token
```

`LIVE_RELOAD_ACCESS_TOKEN` is accepted through:
- `X-Live-Reload-Token` header
- `Authorization: Bearer <token>`
- `?live_reload_token=<token>`
- `?token=<token>` query param

`LIVE_RELOAD_ROUTE_SECRET` is appended to the route prefix, so actual URLs become `/<prefix>/<route_secret>/...`.

## Watch Presets

Use presets to quickly change what the watcher monitors.

```dotenv
LIVE_RELOAD_PRESET=laravel
```

Available presets:

| Preset | Purpose |
| --- | --- |
| `laravel` | Default Laravel development |
| `blade-only` | Blade view work |
| `backend-only` | PHP, routes, config, database, and `.env` work |
| `frontend-assets` | Views, CSS, JS, and public assets |
| `package-development` | Laravel package development |

## Ignore Rules

- `ignore_paths` uses path prefixes and still works as before.
- `ignore_patterns` supports glob patterns (`*`, `?`, `**`) with path-aware matching.
- `.gitignore` patterns are automatically loaded from your project `.gitignore`.

Example:

```php
'ignore_patterns' => [
    '*.log',
    'storage/logs/**',
    'public/build/*',
]
```

## How It Works

1. The watcher scans configured paths.
2. It stores a snapshot of file modification times and sizes.
3. When a file is created, updated, or deleted, it writes a reload signal.
4. The browser client polls `/__live-reload/version`.
5. The browser warns if the watcher process stops.
6. When the version changes, the browser reloads or performs CSS-only updates.
7. Heartbeats are written continuously so status and terminal output can surface watcher health.

Reload signal example:

```json
{
  "version": "1710000000000",
  "changed_file": "resources/views/home.blade.php",
  "changed_type": "updated",
  "changed_at": "2026-06-11 12:30:00"
}
```

Runtime files are written under `storage/framework/live-reload`. The package creates a `.gitignore` inside that directory so runtime files are not committed or deployed.

Injected browser client:

```html
<script src="http://127.0.0.1:8000/__live-reload/client.js" data-live-reload defer></script>
```

If a route secret is enabled the URL includes it:

```html
<script src="http://127.0.0.1:8000/__live-reload/hidden-route-token/client.js" data-live-reload defer></script>
```

## CSS Hot Reload

If a `.css` file changes and CSS hot reload is enabled, the package updates stylesheet URLs with a cache-busting query string instead of reloading the whole page.

Disable it:

```dotenv
LIVE_RELOAD_CSS_HOT_RELOAD=false
```

## Desktop Notifications

Desktop notifications are disabled by default.

Enable them:

```dotenv
LIVE_RELOAD_DESKTOP_NOTIFICATIONS=true
```

The browser may ask for notification permission.

## Security

This package is intended for local development only.

- Disabled unless `APP_ENV=local`
- Injection only runs when `APP_ENV=local`
- JSON responses are not modified
- API paths are not modified
- File downloads are not modified
- Binary and streamed responses are not modified
- Browser responses expose only relative changed file paths
- Absolute server paths and sensitive config values are not exposed
- Runtime state files are written to a git-ignored storage directory
- Loopback is enforced by default (`LIVE_RELOAD_SAFE_MODE=1` and/or `LIVE_RELOAD_ENFORCE_LOOPBACK=1`)
- Optional strict allow-list mode: `LIVE_RELOAD_STRICT_ALLOWLIST=1` to deny access when allow-lists are empty or missing
- Optional token, host/IP allow-lists, and `route_secret` harden endpoint exposure
- `.env` file watching is disabled by default (`LIVE_RELOAD_WATCH_ENV_FILE=0`) and can be enabled explicitly when needed

## Troubleshooting

### Browser Does Not Reload

Run:

```bash
php artisan live-reload:doctor
```

Then check:

- `APP_ENV` is exactly `local`
- `LIVE_RELOAD_ENABLED` is not `false`
- The page is opened from the same URL printed by `live-reload:serve`
- The watcher is running
- The changed file is inside `watch_paths`
- The file extension is inside `watch_extensions`
- The file path is not inside `ignore_paths` or ignored by `ignore_patterns`
- Endpoints are reachable for the current host when hardening is enabled
- `storage/framework/live-reload` is writable
- The page is a `text/html` response

### Routes Or Config Do Not Update

Run:

```bash
php artisan optimize:clear
```

### Watcher Says It Is Already Running

Stop the old terminal process or run:

```bash
php artisan live-reload:clear
```

### Terminal Shows Too Many Request Logs

Use the normal command:

```bash
php artisan live-reload:serve
```

Raw server access logs are hidden by default. To show them:

```bash
php artisan live-reload:serve --show-server-logs
```

## Local-Only Runtime Files

These files are generated at runtime and are excluded from version control by package-managed `.gitignore`:

- `storage/framework/live-reload/reload.json`
- `storage/framework/live-reload/history.json`
- `storage/framework/live-reload/watcher.pid`
- `storage/framework/live-reload/watcher.paused`
- `storage/framework/live-reload/heartbeat.json`
- `storage/framework/live-reload/runtime-config.json`

They remain local-only and should not be committed or uploaded to remote repositories/servers.

This keeps all running-state artifacts local to your environment.

## Testing

Run:

```bash
composer test
```

Or:

```bash
vendor/bin/phpunit
```

## GitHub Actions

The package includes a test workflow:

```txt
.github/workflows/tests.yml
```

It runs the package test suite across supported PHP and Laravel versions.

## Releasing

Use semantic version tags:

```bash
git tag v1.1.0
git push origin v1.1.0
```

Composer and Packagist will use Git tags as package versions.

## License

The MIT License.
