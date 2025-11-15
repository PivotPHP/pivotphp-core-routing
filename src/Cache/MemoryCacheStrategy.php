<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Cache;

use PivotPHP\Routing\Contracts\MemoryCacheInterface;

/**
 * In-Memory Cache Strategy
 *
 * High-performance in-memory caching with memory management.
 */
class MemoryCacheStrategy implements MemoryCacheInterface
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var array<string, string> */
    private array $patterns = [];

    private int $memoryLimit = 10485760; // 10MB default

    /** @var array<string, int> */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
    ];

    /** @var array<string, int> */
    private array $accessCounts = [];

    /**
     * @param int $memoryLimit Memory limit in bytes
     */
    public function __construct(int $memoryLimit = 10485760)
    {
        $this->memoryLimit = $memoryLimit;
    }

    /**
     * {@inheritDoc}
     */
    public function getCompiledPattern(string $pattern): ?string
    {
        if (isset($this->patterns[$pattern])) {
            $this->stats['hits']++;
            $this->accessCounts[$pattern] = ($this->accessCounts[$pattern] ?? 0) + 1;
            return $this->patterns[$pattern];
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setCompiledPattern(string $pattern, string $compiled): void
    {
        $this->patterns[$pattern] = $compiled;
        $this->stats['writes']++;
        $this->accessCounts[$pattern] = 0;

        // Check memory and GC if needed
        if ($this->exceedsMemoryThreshold(0.8)) {
            $this->garbageCollect();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        if (isset($this->cache[$key])) {
            $this->stats['hits']++;
            $this->accessCounts[$key] = ($this->accessCounts[$key] ?? 0) + 1;
            return $this->cache[$key];
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->cache[$key] = $value;
        $this->stats['writes']++;
        $this->accessCounts[$key] = 0;

        // Check memory and GC if needed
        if ($this->exceedsMemoryThreshold(0.8)) {
            $this->garbageCollect();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        unset($this->cache[$key], $this->accessCounts[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->patterns = [];
        $this->accessCounts = [];
        $this->stats = ['hits' => 0, 'misses' => 0, 'writes' => 0];
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        $hitRate = 0.0;
        $total = $this->stats['hits'] + $this->stats['misses'];

        if ($total > 0) {
            $hitRate = ($this->stats['hits'] / $total) * 100;
        }

        return [
            'strategy' => 'memory',
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['writes'],
            'hit_rate' => round($hitRate, 2),
            'memory_usage' => $this->getMemoryUsage(),
            'memory_limit' => $this->memoryLimit,
            'items_count' => count($this->cache) + count($this->patterns),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function warm(array $routes): void
    {
        foreach ($routes as $route) {
            $key = ($route['method'] ?? '') . '::' . ($route['path'] ?? '');
            $this->set($key, $route);

            $pattern = $route['pattern'] ?? null;
            $compiledPattern = $route['compiled_pattern'] ?? null;

            if (is_string($pattern) && is_string($compiledPattern)) {
                $this->setCompiledPattern($pattern, $compiledPattern);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'memory';
    }

    /**
     * {@inheritDoc}
     */
    public function getMemoryUsage(): int
    {
        return strlen(serialize($this->cache)) + strlen(serialize($this->patterns));
    }

    /**
     * {@inheritDoc}
     */
    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }

    /**
     * {@inheritDoc}
     */
    public function setMemoryLimit(int $bytes): void
    {
        $this->memoryLimit = $bytes;
    }

    /**
     * {@inheritDoc}
     */
    public function exceedsMemoryThreshold(float $threshold): bool
    {
        $usage = $this->getMemoryUsage();
        return $usage >= ($this->memoryLimit * $threshold);
    }

    /**
     * {@inheritDoc}
     */
    public function garbageCollect(): int
    {
        // Remove least frequently accessed items (bottom 25%)
        $removed = 0;
        $sortedKeys = $this->accessCounts;
        asort($sortedKeys);

        $toRemove = (int) (count($sortedKeys) * 0.25);

        foreach (array_slice(array_keys($sortedKeys), 0, $toRemove, true) as $key) {
            unset($this->cache[$key], $this->patterns[$key], $this->accessCounts[$key]);
            $removed++;
        }

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function getHitRate(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];

        if ($total === 0) {
            return 0.0;
        }

        return ($this->stats['hits'] / $total) * 100;
    }

    /**
     * {@inheritDoc}
     */
    public function getHits(): int
    {
        return $this->stats['hits'];
    }

    /**
     * {@inheritDoc}
     */
    public function getMisses(): int
    {
        return $this->stats['misses'];
    }

    /**
     * {@inheritDoc}
     */
    public function resetStats(): void
    {
        $this->stats = ['hits' => 0, 'misses' => 0, 'writes' => 0];
    }

    /**
     * {@inheritDoc}
     */
    public function getHotKeys(int $limit = 10): array
    {
        $sorted = $this->accessCounts;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }
}
