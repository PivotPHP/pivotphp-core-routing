<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Plugins;

use PivotPHP\Routing\Contracts\PluginInterface;
use PivotPHP\Routing\Contracts\RouterInterface;

/**
 * Abstract Plugin Base Class
 *
 * Provides common functionality for router plugins.
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected bool $enabled = true;

    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?RouterInterface $router = null;

    /**
     * @param array<string, mixed> $config Plugin configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * {@inheritDoc}
     */
    public function register(RouterInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        // Override in child classes if needed
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getName(): string;

    /**
     * {@inheritDoc}
     */
    abstract public function getVersion(): string;

    /**
     * {@inheritDoc}
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * {@inheritDoc}
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get specific config value
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get default configuration for plugin
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Get router instance
     *
     * @return RouterInterface|null
     */
    protected function getRouter(): ?RouterInterface
    {
        return $this->router;
    }
}
