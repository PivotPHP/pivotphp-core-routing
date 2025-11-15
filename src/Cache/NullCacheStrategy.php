<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Cache;

use PivotPHP\Routing\Contracts\CacheStrategyInterface;

/**
 * Null Cache Strategy
 *
 * No-op cache implementation for when caching is disabled.
 */
class NullCacheStrategy implements CacheStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function getCompiledPattern(string $pattern): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setCompiledPattern(string $pattern, string $compiled): void
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return [
            'strategy' => 'null',
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => 0.0,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function warm(array $routes): void
    {
        // No-op
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'null';
    }
}
