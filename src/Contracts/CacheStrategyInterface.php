<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Cache Strategy Contract
 *
 * Defines the interface for route caching strategies (memory, file, Redis, etc.)
 */
interface CacheStrategyInterface
{
    /**
     * Get cached compiled pattern
     *
     * @param string $pattern Original route pattern
     * @return string|null Compiled pattern or null if not cached
     */
    public function getCompiledPattern(string $pattern): ?string;

    /**
     * Store compiled pattern
     *
     * @param string $pattern Original route pattern
     * @param string $compiled Compiled regex pattern
     * @return void
     */
    public function setCompiledPattern(string $pattern, string $compiled): void;

    /**
     * Get cached route data
     *
     * @param string $key Cache key
     * @return mixed Cached data or null
     */
    public function get(string $key): mixed;

    /**
     * Store route data
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int|null $ttl Time to live in seconds (null = forever)
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Delete cached item
     *
     * @param string $key Cache key
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Clear all cached data
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Get cache statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;

    /**
     * Warm cache with route data
     *
     * @param array<int, array<string, mixed>> $routes Routes to cache
     * @return void
     */
    public function warm(array $routes): void;

    /**
     * Get cache strategy name
     *
     * @return string
     */
    public function getName(): string;
}
