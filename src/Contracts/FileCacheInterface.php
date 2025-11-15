<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * File Cache Contract
 *
 * Specific interface for file-based route caching with PSR-6/PSR-16 compliance.
 */
interface FileCacheInterface extends CacheStrategyInterface
{
    /**
     * Get cache file path
     *
     * @param string $key Cache key
     * @return string
     */
    public function getCacheFilePath(string $key): string;

    /**
     * Check if cache file exists and is valid
     *
     * @param string $key Cache key
     * @return bool
     */
    public function isCacheValid(string $key): bool;

    /**
     * Get cache file modification time
     *
     * @param string $key Cache key
     * @return int|null Timestamp or null if doesn't exist
     */
    public function getCacheTime(string $key): ?int;

    /**
     * Invalidate cache for specific key
     *
     * @param string $key Cache key
     * @return void
     */
    public function invalidate(string $key): void;

    /**
     * Write compiled routes to cache file
     *
     * @param array<int, array<string, mixed>> $routes Routes to cache
     * @return void
     */
    public function writeRoutesCache(array $routes): void;

    /**
     * Read compiled routes from cache file
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function readRoutesCache(): ?array;

    /**
     * Get cache directory path
     *
     * @return string
     */
    public function getCacheDirectory(): string;

    /**
     * Set cache directory path
     *
     * @param string $directory Cache directory
     * @return void
     */
    public function setCacheDirectory(string $directory): void;

    /**
     * Ensure cache directory exists and is writable
     *
     * @return bool
     */
    public function ensureCacheDirectoryExists(): bool;
}
