<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Plugin Contract
 *
 * Defines the interface for router plugins that extend routing functionality.
 */
interface PluginInterface
{
    /**
     * Register plugin with the router
     *
     * Called when plugin is attached to router.
     *
     * @param RouterInterface $router Router instance
     * @return void
     */
    public function register(RouterInterface $router): void;

    /**
     * Boot the plugin
     *
     * Called after all plugins are registered.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Get plugin name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get plugin dependencies (other plugin names)
     *
     * @return array<int, string>
     */
    public function getDependencies(): array;

    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Enable the plugin
     *
     * @return void
     */
    public function enable(): void;

    /**
     * Disable the plugin
     *
     * @return void
     */
    public function disable(): void;

    /**
     * Get plugin configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Set plugin configuration
     *
     * @param array<string, mixed> $config Configuration array
     * @return void
     */
    public function setConfig(array $config): void;
}
