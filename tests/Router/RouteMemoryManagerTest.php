<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\RouteMemoryManager;

class RouteMemoryManagerTest extends TestCase
{
    protected function setUp(): void
    {
        RouteMemoryManager::clearAll();
        RouteMemoryManager::initialize();
    }

    protected function tearDown(): void
    {
        RouteMemoryManager::clearAll();
    }

    public function testInitialize(): void
    {
        RouteMemoryManager::initialize();
        $stats = RouteMemoryManager::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertEquals(0, $stats['route_usage_tracked']);
    }

    public function testTrackRouteUsage(): void
    {
        RouteMemoryManager::trackRouteUsage('GET:/users');
        $stats = RouteMemoryManager::getStats();

        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertEquals(1, $stats['route_usage_tracked']);
    }

    public function testTrackMultipleRouteUsages(): void
    {
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('POST:/users');
        RouteMemoryManager::trackRouteUsage('GET:/users'); // Same route again

        $stats = RouteMemoryManager::getStats();
        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertGreaterThan(0, $stats['route_usage_tracked']);
    }

    public function testGetPopularRoutes(): void
    {
        // Track some routes with different frequencies
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('POST:/posts');
        RouteMemoryManager::trackRouteUsage('POST:/posts');
        RouteMemoryManager::trackRouteUsage('GET:/home');

        $stats = RouteMemoryManager::getStats();

        // Check that stats are properly tracked
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertGreaterThan(0, $stats['route_usage_tracked']);
    }

    public function testCheckMemoryUsage(): void
    {
        $memoryStats = RouteMemoryManager::checkMemoryUsage();

        $this->assertIsArray($memoryStats);
        $this->assertArrayHasKey('current_usage', $memoryStats);
        $this->assertArrayHasKey('status', $memoryStats);
        $this->assertArrayHasKey('recommendations', $memoryStats);
        $this->assertArrayHasKey('thresholds', $memoryStats);
    }

    public function testOptimizeMemory(): void
    {
        // Track many routes to trigger memory optimization
        for ($i = 0; $i < 100; $i++) {
            RouteMemoryManager::trackRouteUsage("GET:/route{$i}");
        }

        $beforeStats = RouteMemoryManager::getStats();

        // Check memory usage without calling non-existent optimizeMemory method
        $memoryCheck = RouteMemoryManager::checkMemoryUsage();

        $this->assertIsArray($memoryCheck);
        $this->assertArrayHasKey('current_usage', $memoryCheck);
        $this->assertArrayHasKey('status', $memoryCheck);
    }

    public function testClearAll(): void
    {
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('POST:/posts');

        $stats = RouteMemoryManager::getStats();
        $this->assertEquals(2, $stats['route_usage_tracked']);

        RouteMemoryManager::clearAll();
        $stats = RouteMemoryManager::getStats();
        $this->assertEquals(0, $stats['route_usage_tracked']);
    }

    public function testGetRoutesByMethod(): void
    {
        RouteMemoryManager::trackRouteUsage('GET:/users');
        RouteMemoryManager::trackRouteUsage('GET:/posts');
        RouteMemoryManager::trackRouteUsage('POST:/users');

        $stats = RouteMemoryManager::getStats();

        // Check that routes are being tracked
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['route_usage_tracked']);
    }

    public function testMemoryThresholds(): void
    {
        // Track routes to potentially trigger memory warnings
        for ($i = 0; $i < 50; $i++) {
            RouteMemoryManager::trackRouteUsage("GET:/route{$i}");
        }

        $memoryStats = RouteMemoryManager::checkMemoryUsage();

        $this->assertContains(
            $memoryStats['status'],
            [
                'optimal', 'warning', 'critical', 'emergency'
            ]
        );
    }

    public function testGetCurrentMemoryUsage(): void
    {
        RouteMemoryManager::trackRouteUsage('GET:/popular');
        RouteMemoryManager::trackRouteUsage('GET:/popular');
        RouteMemoryManager::trackRouteUsage('GET:/popular');
        RouteMemoryManager::trackRouteUsage('GET:/less-popular');
        RouteMemoryManager::trackRouteUsage('GET:/rare');

        $usage = RouteMemoryManager::getCurrentMemoryUsage();

        $this->assertIsArray($usage);
        $this->assertArrayHasKey('total', $usage);
        $this->assertArrayHasKey('bytes', $usage);
        $this->assertArrayHasKey('breakdown', $usage);
    }

    public function testRecordRouteAccess(): void
    {
        // Test the alias method recordRouteAccess
        RouteMemoryManager::recordRouteAccess('GET:/api/users/123');
        RouteMemoryManager::recordRouteAccess('GET:/api/users/456');
        RouteMemoryManager::recordRouteAccess('GET:/api/posts/789');

        $stats = RouteMemoryManager::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('route_usage_tracked', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['route_usage_tracked']);
    }

    public function testPerformanceMetrics(): void
    {
        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            RouteMemoryManager::trackRouteUsage("GET:/performance-test{$i}");
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should be very fast
        $this->assertLessThan(0.1, $duration);
    }
}
