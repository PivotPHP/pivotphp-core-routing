<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Router Contract
 *
 * Defines the interface for routing implementations in PivotPHP.
 * Supports Express.js-inspired API with PSR-7/PSR-15 compliance.
 */
interface RouterInterface
{
    /**
     * Register a GET route
     *
     * @param string $path Route pattern (e.g., '/users/:id')
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function get(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a POST route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function post(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a PUT route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function put(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a DELETE route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function delete(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a PATCH route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function patch(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register an OPTIONS route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function options(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a HEAD route
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function head(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Register a route for any HTTP method
     *
     * @param string $path Route pattern
     * @param callable|array<int, string>|string $handler Route handler
     * @param array<string, mixed> $metadata Route metadata
     * @param callable|array<int, string>|string ...$middlewares Route-specific middlewares
     * @return void
     */
    public static function any(
        string $path,
        callable|array|string $handler,
        array $metadata = [],
        callable|array|string ...$middlewares
    ): void;

    /**
     * Create a route group with shared prefix and middleware
     *
     * @param string $prefix Group prefix (e.g., '/api')
     * @param callable $callback Group definition callback
     * @param callable|array<int, string>|string ...$middlewares Group-level middlewares
     * @return void
     */
    public static function group(
        string $prefix,
        callable $callback,
        callable|array|string ...$middlewares
    ): void;

    /**
     * Identify and match a route for the given HTTP method and path
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Request path
     * @return array<string, mixed>|null Route data or null if not found
     */
    public static function identify(string $method, string $path): ?array;

    /**
     * Get all registered routes
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRoutes(): array;

    /**
     * Clear all registered routes
     *
     * @return void
     */
    public static function clearRoutes(): void;

    /**
     * Register a plugin with the router
     *
     * @param PluginInterface $plugin Plugin instance
     * @return void
     */
    public function registerPlugin(PluginInterface $plugin): void;

    /**
     * Set cache strategy for route compilation
     *
     * @param CacheStrategyInterface $cache Cache strategy implementation
     * @return void
     */
    public function setCacheStrategy(CacheStrategyInterface $cache): void;

    /**
     * Warm the route cache (pre-compile all routes)
     *
     * @return void
     */
    public function warmCache(): void;
}
