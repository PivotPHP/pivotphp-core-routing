<?php

namespace PivotPHP\Routing\Router;

/**
 * Classe RouteCollection para gerenciar coleções de rotas.
 */
class RouteCollection
{
    /**
     * @var Route[]
     */
    private array $routes = [];

    /**
     * @var array<string, Route[]>
     */
    private array $routesByMethod = [];

    /**
     * Adiciona uma rota à coleção.
     *
     * @param  Route $route
     * @return void
     */
    public function add(Route $route): void
    {
        $this->routes[] = $route;

        $method = $route->getMethod();
        if (!isset($this->routesByMethod[$method])) {
            $this->routesByMethod[$method] = [];
        }
        $this->routesByMethod[$method][] = $route;
    }

    /**
     * Encontra uma rota baseada no método e caminho.
     *
     * @param  string $method
     * @param  string $path
     * @return Route|null
     */
    public function match(string $method, string $path): ?Route
    {
        $method = strtoupper($method);

        if (!isset($this->routesByMethod[$method])) {
            return null;
        }

        // Primeiro, procura por rotas estáticas (exatas)
        foreach ($this->routesByMethod[$method] as $route) {
            if ($route->matches($path) && !$route->hasParameters()) {
                return $route;
            }
        }

        // Depois, procura por rotas dinâmicas (com parâmetros)
        foreach ($this->routesByMethod[$method] as $route) {
            if ($route->matches($path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Retorna todas as rotas.
     *
     * @return Route[]
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Retorna rotas por método.
     *
     * @param  string $method
     * @return Route[]
     */
    public function getByMethod(string $method): array
    {
        return $this->routesByMethod[strtoupper($method)] ?? [];
    }

    /**
     * Limpa todas as rotas.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->routesByMethod = [];
    }

    /**
     * Conta o número total de rotas.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->routes);
    }
}
