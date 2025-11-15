<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Plugins;

use PivotPHP\Routing\Contracts\RouterInterface;

/**
 * Debug Plugin
 *
 * Provides debugging tools and route inspection capabilities.
 */
class DebugPlugin extends AbstractPlugin
{
    /** @var array<int, array<string, mixed>> */
    private array $debugLog = [];

    private bool $logEnabled = true;

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'debug';
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
        $logEnabled = $this->getConfigValue('log_enabled', true);
        $this->logEnabled = is_bool($logEnabled) ? $logEnabled : true;
    }

    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function log(string $message, array $context = []): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $this->debugLog[] = [
            'timestamp' => microtime(true),
            'message' => $message,
            'context' => $context,
        ];

        // Limit log size
        $maxEntries = $this->getConfigValue('max_log_entries', 1000);
        if (count($this->debugLog) > $maxEntries) {
            array_shift($this->debugLog);
        }
    }

    /**
     * Get all debug log entries
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLog(): array
    {
        return $this->debugLog;
    }

    /**
     * Clear debug log
     *
     * @return void
     */
    public function clearLog(): void
    {
        $this->debugLog = [];
    }

    /**
     * Dump all registered routes
     *
     * @return array<int, array<string, mixed>>
     */
    public function dumpRoutes(): array
    {
        if ($this->router === null) {
            return [];
        }

        return $this->router::getRoutes();
    }

    /**
     * Get route tree visualization
     *
     * @return array<string, array<int, string>>
     */
    public function getRouteTree(): array
    {
        if ($this->router === null) {
            return [];
        }

        $routes = $this->router::getRoutes();
        /** @var array<string, array<int, string>> */
        $tree = [];

        foreach ($routes as $route) {
            $method = is_string($route['method'] ?? null) ? $route['method'] : 'UNKNOWN';

            if (!isset($tree[$method])) {
                $tree[$method] = [];
            }

            $path = is_string($route['path'] ?? null) ? $route['path'] : '';
            $tree[$method][] = $path;
        }

        return $tree;
    }

    /**
     * Search routes by pattern
     *
     * @param string $pattern Search pattern (supports wildcards)
     * @return array<int, array<string, mixed>>
     */
    public function searchRoutes(string $pattern): array
    {
        if ($this->router === null) {
            return [];
        }

        $routes = $this->router::getRoutes();
        $regex = '#' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '#i';
        $results = [];

        foreach ($routes as $route) {
            $path = $route['path'] ?? '';
            if (is_string($path)) {
                $matchResult = preg_match($regex, $path);
                if ($matchResult === 1) {
                    $results[] = $route;
                }
            }
        }

        return $results;
    }

    /**
     * Get route statistics
     *
     * @return array<string, mixed>
     */
    public function getRouteStats(): array
    {
        if ($this->router === null) {
            return [
                'total_routes' => 0,
                'routes_by_method' => [],
            ];
        }

        $routes = $this->router::getRoutes();
        $byMethod = [];

        foreach ($routes as $route) {
            $method = $route['method'] ?? 'UNKNOWN';
            $byMethod[$method] = ($byMethod[$method] ?? 0) + 1;
        }

        return [
            'total_routes' => count($routes),
            'routes_by_method' => $byMethod,
            'has_parameters' => count(array_filter($routes, function ($r): bool {
                $path = $r['path'] ?? '';
                return is_string($path) && str_contains($path, ':');
            })),
            'static_routes' => count(array_filter($routes, function ($r): bool {
                $path = $r['path'] ?? '';
                return is_string($path) && !str_contains($path, ':');
            })),
        ];
    }

    /**
     * Enable logging
     *
     * @return void
     */
    public function enableLogging(): void
    {
        $this->logEnabled = true;
    }

    /**
     * Disable logging
     *
     * @return void
     */
    public function disableLogging(): void
    {
        $this->logEnabled = false;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'log_enabled' => true,
            'max_log_entries' => 1000,
            'verbose' => false,
        ];
    }
}
