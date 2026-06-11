<?php

return [
    'enabled' => env('LIVE_RELOAD_ENABLED', true),

    'preset' => env('LIVE_RELOAD_PRESET', 'laravel'),

    'poll_interval' => (int) env('LIVE_RELOAD_POLL_INTERVAL', 800),

    'scan_interval' => (int) env('LIVE_RELOAD_SCAN_INTERVAL', 500),

    'debounce_ms' => (int) env('LIVE_RELOAD_DEBOUNCE_MS', 300),

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

    'ignore_paths' => [
        base_path('vendor'),
        base_path('node_modules'),
        storage_path('logs'),
        storage_path('framework/cache'),
        storage_path('framework/sessions'),
        storage_path('framework/views'),
        base_path('bootstrap/cache'),
        base_path('.git'),
        base_path('.idea'),
        base_path('.vscode'),
        base_path('.env.backup'),
    ],

    'route_prefix' => '__live-reload',

    'inject_script' => true,

    'show_console_logs' => true,

    'show_server_logs' => env('LIVE_RELOAD_SHOW_SERVER_LOGS', false),

    'css_hot_reload' => env('LIVE_RELOAD_CSS_HOT_RELOAD', true),

    'multi_tab_sync' => env('LIVE_RELOAD_MULTI_TAB_SYNC', true),

    'auto_open_browser' => env('LIVE_RELOAD_AUTO_OPEN_BROWSER', false),

    'desktop_notifications' => env('LIVE_RELOAD_DESKTOP_NOTIFICATIONS', false),

    'overlay' => [
        'enabled' => env('LIVE_RELOAD_OVERLAY', true),
        'position' => env('LIVE_RELOAD_OVERLAY_POSITION', 'bottom-right'),
        'duration' => (int) env('LIVE_RELOAD_OVERLAY_DURATION', 2200),
    ],

    'storage_path' => storage_path('framework/live-reload'),

    'presets' => [
        'blade-only' => [
            'watch_paths' => [
                resource_path('views'),
            ],
            'watch_extensions' => [
                'php',
                'blade.php',
            ],
        ],

        'backend-only' => [
            'watch_paths' => [
                app_path(),
                base_path('routes'),
                config_path(),
                database_path('migrations'),
                database_path('seeders'),
                base_path('.env'),
            ],
            'watch_extensions' => [
                'php',
                'json',
                'env',
                'xml',
                'yml',
                'yaml',
            ],
        ],

        'frontend-assets' => [
            'watch_paths' => [
                resource_path('views'),
                resource_path('css'),
                resource_path('js'),
                public_path(),
            ],
            'watch_extensions' => [
                'php',
                'blade.php',
                'css',
                'js',
                'json',
            ],
        ],

        'package-development' => [
            'watch_paths' => [
                base_path('src'),
                base_path('config'),
                base_path('routes'),
                base_path('tests'),
            ],
            'watch_extensions' => [
                'php',
                'blade.php',
                'css',
                'js',
                'json',
                'xml',
                'yml',
                'yaml',
            ],
        ],
    ],
];
