<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Plugins;

use PivotPHP\Routing\Contracts\RouterInterface;
use PivotPHP\Routing\Contracts\CacheStrategyInterface;
use PivotPHP\Routing\Cache\MemoryCacheStrategy;

/**
 * Cache Plugin
 *
 * Enhances routing with advanced caching strategies.
 */
class CachePlugin extends AbstractPlugin
{
    private CacheStrategyInterface $cacheStrategy;

    /**
     * @param CacheStrategyInterface|null $cacheStrategy Cache strategy (defaults to MemoryCache)
     * @param array<string, mixed> $config Plugin configuration
     */
    public function __construct(?CacheStrategyInterface $cacheStrategy = null, array $config = [])
    {
        parent::__construct($config);
        $this->cacheStrategy = $cacheStrategy ?? new MemoryCacheStrategy();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'cache';
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
        // Auto-warm cache if enabled
        $autoWarm = $this->getConfigValue('auto_warm', false);
        if (is_bool($autoWarm) && $autoWarm === true) {
            $this->warmCache();
        }
    }

    /**
     * Get cache strategy instance
     *
     * @return CacheStrategyInterface
     */
    public function getCacheStrategy(): CacheStrategyInterface
    {
        return $this->cacheStrategy;
    }

    /**
     * Set cache strategy
     *
     * @param CacheStrategyInterface $strategy Cache strategy
     * @return void
     */
    public function setCacheStrategy(CacheStrategyInterface $strategy): void
    {
        $this->cacheStrategy = $strategy;
    }

    /**
     * Warm cache with current routes
     *
     * @return void
     */
    public function warmCache(): void
    {
        if ($this->router === null) {
            return;
        }

        $routes = $this->router::getRoutes();
        $this->cacheStrategy->warm($routes);
    }

    /**
     * Get cache from strategy
     *
     * @param string $key Cache key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->cacheStrategy->get($key);
    }

    /**
     * Set cache in strategy
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->cacheStrategy->set($key, $value, $ttl);
    }

    /**
     * Clear all cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cacheStrategy->clear();
    }

    /**
     * Get cache statistics
     *
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        return $this->cacheStrategy->getStats();
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'auto_warm' => false,
            'ttl' => 3600, // 1 hour default
        ];
    }
}
