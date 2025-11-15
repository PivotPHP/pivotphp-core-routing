<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use PivotPHP\Routing\Utils\SerializationCache;
use PivotPHP\Routing\Utils\Utils;

/**
 * Advanced Route Memory Manager
 *
 * Manages memory usage of compiled routes with intelligent cleanup,
 * garbage collection, and memory optimization strategies.
 *
 * @package PivotPHP\Core\Routing
 * @since 2.2.0
 */
class RouteMemoryManager
{
    /**
     * Memory usage thresholds
     */
    private const MEMORY_THRESHOLDS = [
        'warning' => 10 * 1024 * 1024,    // 10MB
        'critical' => 20 * 1024 * 1024,   // 20MB
        'emergency' => 50 * 1024 * 1024   // 50MB
    ];

    /**
     * Route usage tracking
     *
     * @var array<string, array{count: int, first_used: int, last_used: int, priority: string}>
     */
    private static array $routeUsage = [];

    /**
     * Memory management statistics
     *
     * @var array<string, int>
     */
    private static array $stats = [
        'gc_cycles' => 0,
        'routes_evicted' => 0,
        'memory_freed' => 0,
        'dynamic_routes_cleaned' => 0,
        'pattern_optimizations' => 0
    ];

    /**
     * Memory optimization strategies
     *
     * @var array<string, callable>
     */
    private static array $optimizationStrategies = [];

    /**
     * Initialize memory manager
     */
    public static function initialize(): void
    {
        self::registerOptimizationStrategies();
        self::startMemoryMonitoring();
    }

    /**
     * Register memory optimization strategies
     */
    private static function registerOptimizationStrategies(): void
    {
        self::$optimizationStrategies = [
            'compress_patterns' => [self::class, 'compressRoutePatterns'],
            'deduplicate_routes' => [self::class, 'deduplicateRoutes'],
            'optimize_parameters' => [self::class, 'optimizeParameterMappings'],
            'cleanup_dynamic' => [self::class, 'cleanupDynamicRoutes'],
            'compress_cache' => [self::class, 'compressRouteCache']
        ];
    }

    /**
     * Check and manage memory usage
     * @return array{current_usage: array<string, mixed>, status: string, recommendations: array<int, string>, thresholds: array<string, int>}
     */
    public static function checkMemoryUsage(): array
    {
        $currentUsage = self::getCurrentMemoryUsage();
        $recommendations = [];

        if ($currentUsage['bytes'] > self::MEMORY_THRESHOLDS['emergency']) {
            $recommendations[] = self::performEmergencyCleanup();
        } elseif ($currentUsage['bytes'] > self::MEMORY_THRESHOLDS['critical']) {
            $recommendations[] = self::performCriticalOptimization();
        } elseif ($currentUsage['bytes'] > self::MEMORY_THRESHOLDS['warning']) {
            $recommendations[] = self::performRoutineOptimization();
        }

        return [
            'current_usage' => $currentUsage,
            'status' => self::getMemoryStatus($currentUsage['bytes']),
            'recommendations' => $recommendations,
            'thresholds' => self::MEMORY_THRESHOLDS
        ];
    }

    /**
     * Get current memory usage of route system
     * @return array{total: string, bytes: int, breakdown: array<string, string>}
     */
    public static function getCurrentMemoryUsage(): array
    {
        $routeCacheSize = self::calculateRouteCacheSize();
        $compiledPatternsSize = self::calculateCompiledPatternsSize();
        $parameterMappingsSize = self::calculateParameterMappingsSize();
        $usageTrackingSize = strlen(serialize(self::$routeUsage));

        $totalBytes = $routeCacheSize + $compiledPatternsSize + $parameterMappingsSize + $usageTrackingSize;

        return [
            'total' => Utils::formatBytes($totalBytes),
            'bytes' => $totalBytes,
            'breakdown' => [
                'route_cache' => Utils::formatBytes($routeCacheSize),
                'compiled_patterns' => Utils::formatBytes($compiledPatternsSize),
                'parameter_mappings' => Utils::formatBytes($parameterMappingsSize),
                'usage_tracking' => Utils::formatBytes($usageTrackingSize)
            ]
        ];
    }

    /**
     * Perform emergency memory cleanup
     */
    private static function performEmergencyCleanup(): string
    {
        $freedMemory = 0;

        // Count routes before clearing for statistics
        $routesCleared = count(self::$routeUsage);

        // Clear all non-essential caches
        RouteCache::clear(); // Use existing clear method
        self::$routeUsage = []; // Clear usage tracking
        $freedMemory += 5 * 1024 * 1024; // Estimate

        // Update statistics for emergency cleanup
        self::$stats['routes_evicted'] += $routesCleared;

        // Remove dynamic routes
        $freedMemory += self::cleanupDynamicRoutes();

        // Force garbage collection
        gc_collect_cycles();
        self::$stats['gc_cycles']++;

        self::$stats['memory_freed'] += $freedMemory;

        return "Emergency cleanup performed. Freed: " . Utils::formatBytes($freedMemory);
    }

    /**
     * Perform critical memory optimization
     */
    private static function performCriticalOptimization(): string
    {
        $optimizations = [];

        // Apply all optimization strategies
        foreach (self::$optimizationStrategies as $name => $strategy) {
            $result = call_user_func($strategy);
            if ($result > 0) {
                $optimizations[] = "$name: " . Utils::formatBytes($result);
                self::$stats['memory_freed'] += $result;
            }
        }

        return "Critical optimizations applied: " . implode(', ', $optimizations);
    }

    /**
     * Perform routine memory optimization
     */
    private static function performRoutineOptimization(): string
    {
        // Clean up old dynamic routes
        $freed = self::cleanupDynamicRoutes();

        // Optimize route patterns
        $freed += self::compressRoutePatterns();

        self::$stats['memory_freed'] += $freed;

        return "Routine optimization completed. Freed: " . Utils::formatBytes($freed);
    }

    /**
     * Cleanup dynamic routes that are rarely used
     */
    private static function cleanupDynamicRoutes(): int
    {
        $freedBytes = 0;
        $currentTime = time();
        $ageThreshold = 3600; // 1 hour
        $usageThreshold = 5; // Minimum 5 uses

        foreach (self::$routeUsage as $routeKey => $usage) {
            $age = $currentTime - $usage['last_used'];
            $useCount = $usage['count'];

            // Remove routes that are old and rarely used
            if ($age > $ageThreshold && $useCount < $usageThreshold) {
                if (self::isDynamicRoute($routeKey)) {
                    $routeSize = self::estimateRouteSize($routeKey);
                    RouteCache::remove($routeKey); // Use existing remove method
                    unset(self::$routeUsage[$routeKey]);

                    $freedBytes += $routeSize;
                    self::$stats['dynamic_routes_cleaned']++;
                    self::$stats['routes_evicted']++;
                }
            }
        }

        return $freedBytes;
    }

    /**
     * Compress route patterns to save memory
     */
    private static function compressRoutePatterns(): int
    {
        // Placeholder implementation - would use RouteCache::getAllPatterns() if available
        return 0;
    }

    /**
     * Deduplicate identical routes
     */
    private static function deduplicateRoutes(): int
    {
        // Placeholder implementation - would use RouteCache::getAllRoutes() if available
        return 0;
    }

    /**
     * Optimize parameter mappings
     */
    private static function optimizeParameterMappings(): int
    {
        // Placeholder implementation - would use RouteCache::getAllParameterMappings() if available
        return 0;
    }

    /**
     * Compress route cache using serialization optimization
     */
    private static function compressRouteCache(): int
    {
        // Placeholder implementation - would use RouteCache::getAllRoutes() if available
        return 0;
    }

    /**
     * Track route usage for optimization decisions
     */
    public static function trackRouteUsage(string $routeKey): void
    {
        if (!isset(self::$routeUsage[$routeKey])) {
            self::$routeUsage[$routeKey] = [
                'count' => 0,
                'first_used' => time(),
                'last_used' => time(),
                'priority' => self::calculateRoutePriority($routeKey)
            ];
        }

        self::$routeUsage[$routeKey]['count']++;
        self::$routeUsage[$routeKey]['last_used'] = time();
    }

    /**
     * Records route access for memory tracking
     * Alias for trackRouteUsage for backward compatibility
     *
     * @param string $routeKey The route key to record
     */
    public static function recordRouteAccess(string $routeKey): void
    {
        self::trackRouteUsage($routeKey);
    }

    /**
     * Calculate route priority for memory management
     */
    private static function calculateRoutePriority(string $routeKey): string
    {
        // Static routes have higher priority
        if (!self::isDynamicRoute($routeKey)) {
            return 'high';
        }

        // API routes are medium priority
        if (strpos($routeKey, '/api/') !== false) {
            return 'medium';
        }

        // Everything else is low priority
        return 'low';
    }

    /**
     * Check if route is dynamic (has parameters)
     */
    private static function isDynamicRoute(string $routeKey): bool
    {
        return strpos($routeKey, '{') !== false || strpos($routeKey, ':') !== false;
    }

    /**
     * Estimate memory size of a route
     */
    private static function estimateRouteSize(string $routeKey): int
    {
        $route = RouteCache::get($routeKey); // Use existing get method

        if (!$route) {
            return 100; // Default estimate
        }

        return strlen(serialize($route));
    }

    /**
     * Calculate route cache size
     */
    private static function calculateRouteCacheSize(): int
    {
        // Use route usage data instead of accessing RouteCache directly
        return SerializationCache::getSerializedSize(
            self::$routeUsage,
            'route_memory_calc'
        );
    }

    /**
     * Calculate compiled patterns size
     */
    private static function calculateCompiledPatternsSize(): int
    {
        // Use empty array as placeholder since getAllPatterns doesn't exist
        return SerializationCache::getSerializedSize(
            [],
            'patterns_memory_calc'
        );
    }

    /**
     * Calculate parameter mappings size
     */
    private static function calculateParameterMappingsSize(): int
    {
        // Use empty array as placeholder since getAllParameterMappings doesn't exist
        return SerializationCache::getSerializedSize(
            [],
            'params_memory_calc'
        );
    }

    /**
     * Get memory status based on usage
     */
    private static function getMemoryStatus(int $bytes): string
    {
        if ($bytes > self::MEMORY_THRESHOLDS['emergency']) {
            return 'emergency';
        } elseif ($bytes > self::MEMORY_THRESHOLDS['critical']) {
            return 'critical';
        } elseif ($bytes > self::MEMORY_THRESHOLDS['warning']) {
            return 'warning';
        } else {
            return 'optimal';
        }
    }

    /**
     * Start memory monitoring
     */
    private static function startMemoryMonitoring(): void
    {
        // Register shutdown function to perform cleanup if needed
        register_shutdown_function([self::class, 'performShutdownCleanup']);
    }

    /**
     * Perform cleanup on shutdown
     */
    public static function performShutdownCleanup(): void
    {
        $usage = self::getCurrentMemoryUsage();

        if ($usage['bytes'] > self::MEMORY_THRESHOLDS['warning']) {
            self::cleanupDynamicRoutes();
        }
    }

    /**
     * Get memory management statistics
     * @return array<string, mixed>
     */
    public static function getStats(): array
    {
        $usage = self::getCurrentMemoryUsage();

        return [
            'current_memory_usage' => $usage,
            'optimization_stats' => self::$stats,
            'route_usage_tracked' => count(self::$routeUsage),
            'memory_status' => self::getMemoryStatus($usage['bytes']),
            'optimization_strategies' => count(self::$optimizationStrategies),
            'recommendations' => self::getOptimizationRecommendations()
        ];
    }

    /**
     * Get optimization recommendations
     * @return array<int, string>
     */
    private static function getOptimizationRecommendations(): array
    {
        $usage = self::getCurrentMemoryUsage();
        $recommendations = [];

        if ($usage['bytes'] > self::MEMORY_THRESHOLDS['warning']) {
            $recommendations[] = 'Consider reducing route cache size';
            $recommendations[] = 'Enable automatic cleanup of dynamic routes';
        }

        if (self::$stats['dynamic_routes_cleaned'] < self::$stats['routes_evicted']) {
            $recommendations[] = 'More dynamic route cleanup may be beneficial';
        }

        if (self::$stats['pattern_optimizations'] === 0) {
            $recommendations[] = 'Route pattern compression could save memory';
        }

        return $recommendations;
    }

    /**
     * Clear all tracking data
     */
    public static function clearAll(): void
    {
        self::$routeUsage = [];
        self::$stats = [
            'gc_cycles' => 0,
            'routes_evicted' => 0,
            'memory_freed' => 0,
            'dynamic_routes_cleaned' => 0,
            'pattern_optimizations' => 0
        ];
    }
}
