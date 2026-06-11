<?php

namespace LaravelSolo\LiveReload\Tests\Unit;

use LaravelSolo\LiveReload\Services\ConfigResolver;
use PHPUnit\Framework\TestCase;

class ConfigResolverTest extends TestCase
{
    public function test_non_laravel_preset_replaces_watch_paths_and_extensions()
    {
        $config = ConfigResolver::resolve([
            'preset' => 'blade-only',
            'watch_paths' => ['app'],
            'watch_extensions' => ['php', 'js'],
            'ignore_paths' => ['vendor'],
            'presets' => [
                'blade-only' => [
                    'watch_paths' => ['resources/views'],
                    'watch_extensions' => ['blade.php'],
                    'ignore_paths' => ['storage/framework/views'],
                ],
            ],
        ]);

        $this->assertSame(['resources/views'], $config['watch_paths']);
        $this->assertSame(['blade.php'], $config['watch_extensions']);
        $this->assertSame(['vendor', 'storage/framework/views'], $config['ignore_paths']);
    }

    public function test_laravel_preset_keeps_base_configuration()
    {
        $config = ConfigResolver::resolve([
            'preset' => 'laravel',
            'watch_paths' => ['app'],
            'watch_extensions' => ['php'],
            'presets' => [
                'blade-only' => [
                    'watch_paths' => ['resources/views'],
                    'watch_extensions' => ['blade.php'],
                ],
            ],
        ]);

        $this->assertSame(['app'], $config['watch_paths']);
        $this->assertSame(['php'], $config['watch_extensions']);
    }
}
