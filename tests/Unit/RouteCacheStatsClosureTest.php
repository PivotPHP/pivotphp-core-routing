<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\RouteCache;

/**
 * Cobre que Router::getStats()/RouteCache::getDebugInfo() não serializam rotas
 * (SPEC-047 — serialize() é proibido para Closure).
 */
class RouteCacheStatsClosureTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear();
        RouteCache::clear();
    }

    protected function tearDown(): void
    {
        Router::clear();
        RouteCache::clear();
    }

    /**
     * @test
     */
    public function testGetStatsWithClosureHandlerDoesNotThrow(): void
    {
        Router::get('/closure', function () {
            return 1;
        });

        $stats = Router::getStats();

        $this->assertIsArray($stats);
        $this->assertSame(1, $stats['total_routes']);
    }

    /**
     * @test
     */
    public function testGetDebugInfoWithClosureHandlerDoesNotThrow(): void
    {
        Router::get('/closure', function () {
            return 1;
        });

        $debug = RouteCache::getDebugInfo();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('cache_size', $debug);
        $this->assertSame(1, $debug['cache_size']['routes']);
    }
}
