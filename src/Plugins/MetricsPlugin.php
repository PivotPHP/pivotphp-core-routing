<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Plugins;

use PivotPHP\Routing\Contracts\RouterInterface;

/**
 * Metrics Plugin
 *
 * Collects and reports routing performance metrics.
 */
class MetricsPlugin extends AbstractPlugin
{
    /** @var array<string, array<string, mixed>> */
    private array $metrics = [];

    private int $totalMatches = 0;
    private float $totalMatchTime = 0.0;

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'metrics';
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        // Initialize metrics collection
    }

    /**
     * Record a route match
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param float $time Match time in milliseconds
     * @return void
     */
    public function recordMatch(string $method, string $path, float $time): void
    {
        $key = "{$method}::{$path}";

        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'method' => $method,
                'path' => $path,
                'hits' => 0,
                'total_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
            ];
        }

        $this->metrics[$key]['hits']++;
        $this->metrics[$key]['total_time'] += $time;
        $this->metrics[$key]['min_time'] = min($this->metrics[$key]['min_time'], $time);
        $this->metrics[$key]['max_time'] = max($this->metrics[$key]['max_time'], $time);

        $this->totalMatches++;
        $this->totalMatchTime += $time;
    }

    /**
     * Get all collected metrics
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $avgTime = $this->totalMatches > 0
            ? $this->totalMatchTime / $this->totalMatches
            : 0.0;

        return [
            'total_matches' => $this->totalMatches,
            'total_time' => $this->totalMatchTime,
            'average_time' => $avgTime,
            'routes' => $this->getRouteMetrics(),
            'top_routes' => $this->getTopRoutes(10),
            'slowest_routes' => $this->getSlowestRoutes(10),
        ];
    }

    /**
     * Get metrics for individual routes
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRouteMetrics(): array
    {
        $result = [];

        foreach ($this->metrics as $key => $data) {
            $result[$key] = [
                'method' => $data['method'],
                'path' => $data['path'],
                'hits' => $data['hits'],
                'avg_time' => $data['hits'] > 0 ? $data['total_time'] / $data['hits'] : 0.0,
                'min_time' => $data['min_time'] === PHP_FLOAT_MAX ? 0.0 : $data['min_time'],
                'max_time' => $data['max_time'],
            ];
        }

        return $result;
    }

    /**
     * Get top N most accessed routes
     *
     * @param int $limit Number of routes to return
     * @return array<string, array<string, mixed>>
     */
    public function getTopRoutes(int $limit = 10): array
    {
        $sorted = $this->metrics;
        uasort($sorted, fn($a, $b) => $b['hits'] <=> $a['hits']);

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Get N slowest routes
     *
     * @param int $limit Number of routes to return
     * @return array<string, array<string, mixed>>
     */
    public function getSlowestRoutes(int $limit = 10): array
    {
        $sorted = $this->metrics;
        uasort($sorted, function ($a, $b) {
            $avgA = $a['hits'] > 0 ? $a['total_time'] / $a['hits'] : 0.0;
            $avgB = $b['hits'] > 0 ? $b['total_time'] / $b['hits'] : 0.0;
            return $avgB <=> $avgA;
        });

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Reset all metrics
     *
     * @return void
     */
    public function reset(): void
    {
        $this->metrics = [];
        $this->totalMatches = 0;
        $this->totalMatchTime = 0.0;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'track_slow_routes' => true,
            'slow_threshold_ms' => 10.0,
        ];
    }
}
