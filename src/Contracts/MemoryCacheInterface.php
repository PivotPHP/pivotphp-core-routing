<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Memory Cache Contract
 *
 * In-memory caching strategy with memory management capabilities.
 */
interface MemoryCacheInterface extends CacheStrategyInterface
{
    /**
     * Get current memory usage for cache
     *
     * @return int Bytes
     */
    public function getMemoryUsage(): int;

    /**
     * Get memory usage limit
     *
     * @return int Bytes
     */
    public function getMemoryLimit(): int;

    /**
     * Set memory usage limit
     *
     * @param int $bytes Memory limit in bytes
     * @return void
     */
    public function setMemoryLimit(int $bytes): void;

    /**
     * Check if memory usage exceeds threshold
     *
     * @param float $threshold Percentage (0.0 to 1.0)
     * @return bool
     */
    public function exceedsMemoryThreshold(float $threshold): bool;

    /**
     * Perform garbage collection on cache
     *
     * @return int Number of items removed
     */
    public function garbageCollect(): int;

    /**
     * Get cache hit rate
     *
     * @return float Percentage (0.0 to 100.0)
     */
    public function getHitRate(): float;

    /**
     * Get number of cache hits
     *
     * @return int
     */
    public function getHits(): int;

    /**
     * Get number of cache misses
     *
     * @return int
     */
    public function getMisses(): int;

    /**
     * Reset cache statistics
     *
     * @return void
     */
    public function resetStats(): void;

    /**
     * Get most frequently accessed items
     *
     * @param int $limit Number of items to return
     * @return array<string, int>
     */
    public function getHotKeys(int $limit = 10): array;
}
