# Laravel Solo Live Reload

A PHP-only live reload package for Laravel local development.

Laravel Solo Live Reload watches your Laravel files, injects a small browser client into local HTML responses, and reloads the browser when files change. It does not require Node.js, npm, Vite, Webpack, Laravel Mix, BrowserSync, socket.io, or any frontend build tool.

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
- Optional browser desktop notifications
- Local status dashboard
- Recent change history in status output
- Setup diagnostics with `live-reload:doctor`
- Pipeline self-test with `live-reload:test`
- Package summary with `live-reload:about`
- Optional browser auto-open with `--open`
- Clean terminal output by default
- Optional raw server logs with `--show-server-logs`
- Configurable watch paths, extensions, ignored paths, and presets
- Windows, macOS, and Linux support
- Laravel 8, 9, 10, 11, and 12 support

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

Now edit a watched file, for example:

```txt
resources/views/layouts/sidebar.blade.php
```

The browser reloads automatically.

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

By default, raw `php artisan serve` access logs are hidden because the live reload client polls the server frequently. Use this option only when debugging requests.

### Run Watcher Separately

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
php artisan live-reload:watch
```

### Status

```bash
php artisan live-reload:status
```

Shows whether live reload is enabled, watcher state, watched paths, ignored paths, intervals, and the last changed file.

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

Shows package name, environment, enabled state, preset, route prefix, status URL, config path, and storage path.

### Self-Test

```bash
php artisan live-reload:test
```

Checks the local environment gate, storage writability, route registration, reload signal write/read, and recent change history.

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

## Default Watched Paths

```php
'watch_paths' => [
    app_path(),
    base_path('routes'),
    resource_path('views'),
    resource_path('css'),
    resource_path('js'),
    public_path(),
    config_path(),
    database_path('migrations'),
    database_path('seeders'),
    base_path('lang'),
    base_path('.env'),
],
```

## Default Watched Extensions

```php
'watch_extensions' => [
    'php',
    'blade.php',
    'css',
    'js',
    'json',
    'env',
    'xml',
    'yml',
    'yaml',
],
```

## How It Works

1. The watcher scans configured paths.
2. It stores a snapshot of file modification times and sizes.
3. When a file is created, updated, or deleted, it writes a reload signal.
4. The browser client polls `/__live-reload/version`.
5. The browser warns if the watcher process stops.
6. When the version changes, the browser reloads.

Reload signal example:

```json
{
  "version": "1710000000000",
  "changed_file": "resources/views/home.blade.php",
  "changed_type": "updated",
  "changed_at": "2026-06-11 12:30:00"
}
```

Runtime files are written under `storage/framework/live-reload`. The package creates a `.gitignore` inside that directory so `reload.json`, `history.json`, and `watcher.pid` are not committed or deployed.

Injected browser client:

```html
<script src="http://127.0.0.1:8000/__live-reload/client.js" data-live-reload defer></script>
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
- The file path is not inside `ignore_paths`
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
