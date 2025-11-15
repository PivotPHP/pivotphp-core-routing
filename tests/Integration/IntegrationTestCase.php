<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests
 *
 * Provides common utilities and setup for integration testing scenarios.
 * Simplified version for routing package.
 *
 * @group integration
 */
abstract class IntegrationTestCase extends TestCase
{
    protected array $testConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestEnvironment();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestEnvironment();
        parent::tearDown();
    }

    /**
     * Setup test environment with clean state
     */
    protected function setupTestEnvironment(): void
    {
        // Clear any global state
        $this->clearGlobalState();
    }

    /**
     * Cleanup test environment
     */
    protected function cleanupTestEnvironment(): void
    {
        // Force garbage collection
        gc_collect_cycles();
    }

    /**
     * Measure execution time of a callback
     *
     * @param callable $callback Callback to measure
     * @return float Execution time in milliseconds
     */
    protected function measureExecutionTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return (microtime(true) - $start) * 1000; // Convert to milliseconds
    }

    /**
     * Assert performance metrics are within acceptable limits
     *
     * @param array<string, mixed> $metrics Performance metrics
     * @param array<string, array<string, mixed>> $limits Performance limits
     * @return void
     */
    protected function assertPerformanceWithinLimits(array $metrics, array $limits): void
    {
        foreach ($limits as $metric => $limit) {
            $this->assertArrayHasKey($metric, $metrics, "Metric '{$metric}' not found in performance data");

            if (isset($limit['max'])) {
                $this->assertLessThanOrEqual(
                    $limit['max'],
                    $metrics[$metric],
                    "Metric '{$metric}' ({$metrics[$metric]}) exceeds maximum limit ({$limit['max']})"
                );
            }

            if (isset($limit['min'])) {
                $this->assertGreaterThanOrEqual(
                    $limit['min'],
                    $metrics[$metric],
                    "Metric '{$metric}' ({$metrics[$metric]}) below minimum limit ({$limit['min']})"
                );
            }
        }
    }

    /**
     * Apply test configuration
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    protected function applyTestConfiguration(array $config): void
    {
        $this->testConfig = array_merge($this->testConfig, $config);
    }

    /**
     * Clear global state between tests
     */
    protected function clearGlobalState(): void
    {
        // Clear any static variables or global state
        // Reset error handlers if needed
    }

    /**
     * Assert memory usage is within acceptable limits
     *
     * @param int $maxMemoryMB Maximum memory in MB
     * @return void
     */
    protected function assertMemoryUsageWithinLimits(int $maxMemoryMB = 100): void
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // Convert to MB
        $this->assertLessThan(
            $maxMemoryMB,
            $memoryUsage,
            "Memory usage ({$memoryUsage}MB) exceeds limit ({$maxMemoryMB}MB)"
        );
    }

    /**
     * Create test middleware for routing tests
     *
     * @param string $name Middleware name
     * @return callable
     */
    protected function createNamedMiddleware(string $name): callable
    {
        return match ($name) {
            'logging' => function ($req, $res, $next) {
                return $next($req, $res);
            },
            'timing' => function ($req, $res, $next) {
                $start = microtime(true);
                $response = $next($req, $res);
                $duration = (microtime(true) - $start) * 1000;
                return $response;
            },
            'auth' => function ($req, $res, $next) {
                return $next($req, $res);
            },
            default => function ($req, $res, $next) {
                return $next($req, $res);
            }
        };
    }
}
