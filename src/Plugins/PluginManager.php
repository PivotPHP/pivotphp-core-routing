<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Plugins;

use PivotPHP\Routing\Contracts\PluginInterface;
use PivotPHP\Routing\Contracts\RouterInterface;

/**
 * Plugin Manager
 *
 * Manages router plugins lifecycle: registration, dependency resolution, and execution.
 */
class PluginManager
{
    /** @var array<string, PluginInterface> */
    protected array $plugins = [];

    /** @var array<string, bool> */
    protected array $booted = [];

    protected RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Register a plugin
     *
     * @param PluginInterface $plugin Plugin to register
     * @return void
     * @throws \RuntimeException
     */
    public function register(PluginInterface $plugin): void
    {
        $name = $plugin->getName();

        if (isset($this->plugins[$name])) {
            throw new \RuntimeException("Plugin '{$name}' is already registered");
        }

        // Check dependencies
        $this->checkDependencies($plugin);

        // Register plugin
        $this->plugins[$name] = $plugin;
        $plugin->register($this->router);

        // Mark as not booted
        $this->booted[$name] = false;
    }

    /**
     * Boot all plugins
     *
     * @return void
     */
    public function bootAll(): void
    {
        // Sort plugins by dependencies
        $sorted = $this->topologicalSort();

        foreach ($sorted as $name) {
            $this->boot($name);
        }
    }

    /**
     * Boot specific plugin
     *
     * @param string $name Plugin name
     * @return void
     */
    public function boot(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            throw new \RuntimeException("Plugin '{$name}' is not registered");
        }

        if ($this->booted[$name]) {
            return; // Already booted
        }

        $plugin = $this->plugins[$name];

        if (!$plugin->isEnabled()) {
            return; // Plugin is disabled
        }

        // Boot dependencies first
        foreach ($plugin->getDependencies() as $dependency) {
            $this->boot($dependency);
        }

        // Boot plugin
        $plugin->boot();
        $this->booted[$name] = true;
    }

    /**
     * Get plugin by name
     *
     * @param string $name Plugin name
     * @return PluginInterface|null
     */
    public function get(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * Check if plugin exists
     *
     * @param string $name Plugin name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * Get all registered plugins
     *
     * @return array<string, PluginInterface>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * Get enabled plugins
     *
     * @return array<string, PluginInterface>
     */
    public function getEnabled(): array
    {
        return array_filter($this->plugins, fn($plugin) => $plugin->isEnabled());
    }

    /**
     * Enable plugin
     *
     * @param string $name Plugin name
     * @return void
     */
    public function enable(string $name): void
    {
        $plugin = $this->get($name);
        if ($plugin !== null) {
            $plugin->enable();
        }
    }

    /**
     * Disable plugin
     *
     * @param string $name Plugin name
     * @return void
     */
    public function disable(string $name): void
    {
        $plugin = $this->get($name);
        if ($plugin !== null) {
            $plugin->disable();
        }
    }

    /**
     * Check plugin dependencies
     *
     * @param PluginInterface $plugin Plugin to check
     * @return void
     * @throws \RuntimeException
     */
    protected function checkDependencies(PluginInterface $plugin): void
    {
        foreach ($plugin->getDependencies() as $dependency) {
            if (!isset($this->plugins[$dependency])) {
                throw new \RuntimeException(
                    "Plugin '{$plugin->getName()}' requires '{$dependency}' plugin"
                );
            }
        }
    }

    /**
     * Topological sort of plugins by dependencies
     *
     * @return array<int, string>
     * @throws \RuntimeException
     */
    protected function topologicalSort(): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($this->plugins) as $name) {
            $this->topologicalSortVisit($name, $visited, $visiting, $sorted);
        }

        return $sorted;
    }

    /**
     * Visit node in topological sort
     *
     * @param string $name Plugin name
     * @param array<string, bool> &$visited Visited nodes
     * @param array<string, bool> &$visiting Currently visiting nodes
     * @param array<int, string> &$sorted Sorted result
     * @return void
     * @throws \RuntimeException
     */
    protected function topologicalSortVisit(
        string $name,
        array &$visited,
        array &$visiting,
        array &$sorted
    ): void {
        if (isset($visited[$name])) {
            return; // Already visited
        }

        if (isset($visiting[$name])) {
            throw new \RuntimeException("Circular dependency detected in plugin '{$name}'");
        }

        $visiting[$name] = true;
        $plugin = $this->plugins[$name];

        // Visit dependencies
        foreach ($plugin->getDependencies() as $dependency) {
            $this->topologicalSortVisit($dependency, $visited, $visiting, $sorted);
        }

        $visited[$name] = true;
        unset($visiting[$name]);
        $sorted[] = $name;
    }
}
