<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Cache\FileCacheStrategy;

/**
 * Cobre que FileCacheStrategy recusa closures explicitamente (SPEC-048).
 */
class FileCacheStrategyClosureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pivotphp-route-cache-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    /**
     * @test
     */
    public function testSetWithClosureThrowsExplicitly(): void
    {
        $cache = new FileCacheStrategy($this->dir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot cache closures');

        $cache->set('routes', [['handler' => function () {
            return 1;
        }]]);
    }

    /**
     * @test
     */
    public function testSetWithArrayCallableRoundTrips(): void
    {
        $cache = new FileCacheStrategy($this->dir);

        $cache->set('routes', [['handler' => ['SomeController', 'index']]]);

        $this->assertSame([['handler' => ['SomeController', 'index']]], $cache->get('routes'));
    }
}
