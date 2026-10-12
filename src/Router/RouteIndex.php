<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use PivotPHP\Routing\Router\Resolution\RouteMatched;

/**
 * Índice em memória de alta performance para resolução de rotas (O(1) estático + prefixos dinâmicos).
 */
final class RouteIndex
{
    /**
     * Rotas estáticas indexadas por método e caminho normalizado: [METHOD][NORMALIZED_PATH] => route array.
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $staticRoutes = [];

    /**
     * Cache de RouteMatched imutável sob demanda para rotas estáticas.
     * @var array<string, array<string, RouteMatched>>
     */
    private array $staticMatchedCache = [];

    /**
     * Métodos permitidos para cada caminho estático: [NORMALIZED_PATH][METHOD] => true.
     * @var array<string, array<string, bool>>
     */
    private array $staticPathMethods = [];

    /**
     * Rotas dinâmicas indexadas por método e prefixo literal: [METHOD][PREFIX][] => route array.
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     */
    private array $dynamicRoutesByPrefix = [];

    /**
     * Adiciona uma rota ao índice a partir de array (retrocompatível).
     *
     * @param array<string, mixed> $route
     */
    public function addRoute(array $route): void
    {
        $method = is_string($route['method'] ?? null) ? $route['method'] : 'GET';
        $path = is_string($route['path'] ?? null) ? $route['path'] : '/';
        $hasParameters = ($route['has_parameters'] ?? false) === true;

        $this->addRouteDirect($method, $path, $route, $hasParameters);
    }

    /**
     * Adiciona uma rota diretamente com tipos primitivos (hot-path de registro com zero overhead).
     *
     * @param array<string, mixed> $route
     */
    public function addRouteDirect(string $method, string $path, array $route, bool $hasParameters): void
    {
        $normalizedPath = $path !== '/' && str_ends_with($path, '/') ? rtrim($path, '/') : $path;

        if (!$hasParameters) {
            $this->staticPathMethods[$normalizedPath][$method] = true;
            $this->staticRoutes[$method][$normalizedPath] = $route;
            return;
        }

        $prefix = self::extractLiteralPrefix($path);
        $this->dynamicRoutesByPrefix[$method][$prefix][] = $route;
    }

    /**
     * Tenta encontrar uma rota casando com o método e caminho fornecidos.
     */
    public function match(string $method, string $normalizedPath, string $rawPath): ?RouteMatched
    {
        $method = strtoupper($method);

        // 1. O(1) Match estático imediato
        if (isset($this->staticRoutes[$method][$normalizedPath])) {
            if (isset($this->staticMatchedCache[$method][$normalizedPath])) {
                return $this->staticMatchedCache[$method][$normalizedPath];
            }

            $route = $this->staticRoutes[$method][$normalizedPath];
            /** @var array<int, callable> $middlewares */
            $middlewares = is_array($route['middlewares'] ?? null) ? $route['middlewares'] : [];
            /** @var array<string, mixed> $metadata */
            $metadata = is_array($route['metadata'] ?? null) ? $route['metadata'] : [];

            $matched = new RouteMatched($route, [], $middlewares, $metadata);
            $this->staticMatchedCache[$method][$normalizedPath] = $matched;

            return $matched;
        }

        // 2. Match dinâmico com busca direcionada por prefixos candidatos
        $candidatePrefixes = self::getCandidatePrefixes($normalizedPath);

        foreach ($candidatePrefixes as $prefix) {
            if (!isset($this->dynamicRoutesByPrefix[$method][$prefix])) {
                continue;
            }

            foreach ($this->dynamicRoutesByPrefix[$method][$prefix] as $route) {
                $matched = self::matchRoutePattern($route, $rawPath);
                if ($matched !== null) {
                    /** @var array<string, string> $matchedParams */
                    $matchedParams = is_array($matched['matched_params'] ?? null) ? $matched['matched_params'] : [];
                    /** @var array<int, callable> $middlewares */
                    $middlewares = is_array($matched['middlewares'] ?? null) ? $matched['middlewares'] : [];
                    /** @var array<string, mixed> $metadata */
                    $metadata = is_array($matched['metadata'] ?? null) ? $matched['metadata'] : [];

                    return new RouteMatched(
                        $matched,
                        $matchedParams,
                        $middlewares,
                        $metadata
                    );
                }
            }
        }

        return null;
    }

    /**
     * Retorna todos os métodos HTTP aceitos para o caminho fornecido (para 405 / OPTIONS).
     *
     * @return array<int, string>
     */
    public function getAllowedMethodsForPath(string $normalizedPath, string $rawPath): array
    {
        $allowed = [];

        // 1. Métodos estáticos
        if (isset($this->staticPathMethods[$normalizedPath])) {
            $allowed = array_keys($this->staticPathMethods[$normalizedPath]);
        }

        // 2. Métodos dinâmicos
        $candidatePrefixes = self::getCandidatePrefixes($normalizedPath);
        foreach ($candidatePrefixes as $prefix) {
            foreach ($this->dynamicRoutesByPrefix as $method => $routesByPrefix) {
                if (in_array($method, $allowed, true) || !isset($routesByPrefix[$prefix])) {
                    continue;
                }

                foreach ($routesByPrefix[$prefix] as $route) {
                    if (self::matchRoutePattern($route, $rawPath) !== null) {
                        $allowed[] = $method;
                        break;
                    }
                }
            }
        }

        // SPEC-072: Se houver GET mas não HEAD, adicionar HEAD
        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        return $allowed;
    }

    /**
     * Extrai o prefixo literal mais longo antes do primeiro parâmetro dinâmico.
     */
    public static function extractLiteralPrefix(string $path): string
    {
        $pos = strcspn($path, ':{(');
        if ($pos >= strlen($path)) {
            return $path !== '/' && str_ends_with($path, '/') ? rtrim($path, '/') : $path;
        }

        for ($i = $pos - 1; $i > 0; $i--) {
            if ($path[$i] === '/') {
                return substr($path, 0, $i);
            }
        }

        return '/';
    }

    /**
     * Gera lista de prefixos candidatos de maior especificidade para menor.
     * Ex: /api/resource1/42 => ['/api/resource1/42', '/api/resource1', '/api', '/']
     *
     * @return array<int, string>
     */
    public static function getCandidatePrefixes(string $path): array
    {
        if ($path === '/' || $path === '') {
            return ['/'];
        }

        $candidates = [];
        $current = $path;

        while (($lastSlash = strrpos($current, '/')) !== false && $lastSlash > 0) {
            $candidates[] = $current;
            $current = substr($current, 0, $lastSlash);
        }

        if ($current !== '' && $current !== '/') {
            $candidates[] = $current;
        }

        $candidates[] = '/';

        return $candidates;
    }

    /**
     * Normaliza um path para comparação (remove trailing slash, exceto na raiz).
     */
    public static function normalizePathForMatching(string $path): string
    {
        if ($path !== '/' && $path !== '' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Executa o match de regex de uma rota dinâmica.
     *
     * @param array<string, mixed> $route
     * @return array<string, mixed>|null
     */
    private static function matchRoutePattern(array $route, string $path): ?array
    {
        $pattern = $route['pattern'] ?? null;
        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        if (preg_match($pattern, $path, $matches) === 1) {
            $parameters = $route['parameters'] ?? [];
            if (is_array($parameters) && count($parameters) > 0 && count($matches) > 1) {
                $matchedParams = [];
                foreach ($parameters as $param) {
                    if (!is_array($param)) {
                        continue;
                    }
                    $pos = $param['position'] ?? null;
                    $name = $param['name'] ?? null;
                    if (is_int($pos) && is_string($name) && isset($matches[$pos + 1])) {
                        $matchedParams[$name] = $matches[$pos + 1];
                    }
                }
                $route['matched_params'] = $matchedParams;
            }

            return $route;
        }

        return null;
    }
}
