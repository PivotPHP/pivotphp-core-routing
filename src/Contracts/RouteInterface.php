<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Route Contract
 *
 * Represents an individual route with pattern matching and parameter extraction.
 */
interface RouteInterface
{
    /**
     * Get the route pattern
     *
     * @return string
     */
    public function getPattern(): string;

    /**
     * Get the compiled regex pattern
     *
     * @return string
     */
    public function getCompiledPattern(): string;

    /**
     * Get the route handler
     *
     * @return callable|array<int, string>|string
     */
    public function getHandler(): callable|array|string;

    /**
     * Get route metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get route-specific middlewares
     *
     * @return array<int, callable|array<int, string>|string>
     */
    public function getMiddlewares(): array;

    /**
     * Check if path matches this route
     *
     * @param string $path Request path
     * @return bool
     */
    public function matches(string $path): bool;

    /**
     * Extract parameters from the given path
     *
     * @param string $path Request path
     * @return array<string, string>
     */
    public function extractParams(string $path): array;

    /**
     * Get parameter names defined in route pattern
     *
     * @return array<int, string>
     */
    public function getParamNames(): array;

    /**
     * Add middleware to this route
     *
     * @param callable|array<int, string>|string ...$middlewares
     * @return self
     */
    public function middleware(callable|array|string ...$middlewares): self;

    /**
     * Set route metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return self
     */
    public function setMetadata(string $key, mixed $value): self;

    /**
     * Get specific metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed;
}
