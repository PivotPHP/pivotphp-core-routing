<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Contracts;

/**
 * Route Collection Contract
 *
 * Manages a collection of routes with indexing and matching capabilities.
 */
interface RouteCollectionInterface
{
    /**
     * Add a route to the collection
     *
     * @param string $method HTTP method
     * @param RouteInterface $route Route instance
     * @return void
     */
    public function add(string $method, RouteInterface $route): void;

    /**
     * Get routes for a specific HTTP method
     *
     * @param string $method HTTP method
     * @return array<int, RouteInterface>
     */
    public function getByMethod(string $method): array;

    /**
     * Get all routes
     *
     * @return array<string, array<int, RouteInterface>>
     */
    public function all(): array;

    /**
     * Match a route for the given method and path
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return RouteInterface|null
     */
    public function match(string $method, string $path): ?RouteInterface;

    /**
     * Clear all routes from the collection
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Count total routes
     *
     * @return int
     */
    public function count(): int;

    /**
     * Count routes for a specific method
     *
     * @param string $method HTTP method
     * @return int
     */
    public function countByMethod(string $method): int;

    /**
     * Check if collection has any routes
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Check if collection has routes for a specific method
     *
     * @param string $method HTTP method
     * @return bool
     */
    public function hasMethod(string $method): bool;
}
