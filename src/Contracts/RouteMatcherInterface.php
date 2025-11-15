<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Route Matcher Contract
 *
 * Defines strategy for matching routes against request paths.
 * Allows different matching implementations (optimized, traditional, etc.)
 */
interface RouteMatcherInterface
{
    /**
     * Match a route for the given method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<int, array<string, mixed>> $routes Available routes
     * @return array<string, mixed>|null Matched route data or null
     */
    public function match(string $method, string $path, array $routes): ?array;

    /**
     * Get matcher name/type
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get matcher priority (higher = try first)
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Check if this matcher can handle the given route
     *
     * @param array<string, mixed> $route Route data
     * @return bool
     */
    public function canHandle(array $route): bool;
}
