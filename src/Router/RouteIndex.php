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
     * Instâncias imutáveis pré-alocadas de RouteMatched para rotas estáticas sem parâmetros.
     * Elimina alocação de objetos no hot-path!
     * @var array<string, array<string, RouteMatched>>
     */
    private array $staticMatched = [];

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
     * Todas as rotas dinâmicas indexadas por prefixo literal para cálculo de allowedMethods: [PREFIX][] => route array.
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $allDynamicRoutesByPrefix = [];

    /**
     * Adiciona uma rota ao índice.
     *
     * @param array<string, mixed> $route
     */
    public function addRoute(array $route): void
    {
        $methodRaw = $route['method'] ?? 'GET';
        $method = is_string($methodRaw) ? strtoupper($methodRaw) : 'GET';
        $pathRaw = $route['path'] ?? '/';
        $path = is_string($pathRaw) ? $pathRaw : '/';
        $normalizedPath = self::normalizePathForMatching($path);
        $hasParameters = ($route['has_parameters'] ?? false) === true;

        if (!$hasParameters) {
            $this->staticPathMethods[$normalizedPath][$method] = true;

            /** @var array<int, callable> $middlewares */
            $middlewares = is_array($route['middlewares'] ?? null) ? $route['middlewares'] : [];
            /** @var array<string, mixed> $metadata */
            $metadata = is_array($route['metadata'] ?? null) ? $route['metadata'] : [];

            $this->staticMatched[$method][$normalizedPath] = new RouteMatched(
                $route,
                [],
                $middlewares,
                $metadata
            );
            return;
        }

        $prefix = self::extractLiteralPrefix($path);
        $this->dynamicRoutesByPrefix[$method][$prefix][] = $route;
        $this->allDynamicRoutesByPrefix[$prefix][] = $route;
    }

    /**
     * Tenta encontrar uma rota casando com o método e caminho fornecidos.
     */
    public function match(string $method, string $normalizedPath, string $rawPath): ?RouteMatched
    {
        $method = strtoupper($method);

        // 1. O(1) Match estático imediato (com RouteMatched pré-alocado)
        if (isset($this->staticMatched[$method][$normalizedPath])) {
            return $this->staticMatched[$method][$normalizedPath];
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
            if (!isset($this->allDynamicRoutesByPrefix[$prefix])) {
                continue;
            }

            foreach ($this->allDynamicRoutesByPrefix[$prefix] as $route) {
                $methodRaw = $route['method'] ?? 'GET';
                $method = is_string($methodRaw) ? $methodRaw : 'GET';
                if (in_array($method, $allowed, true)) {
                    continue;
                }

                if (self::matchRoutePattern($route, $rawPath) !== null) {
                    $allowed[] = $method;
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
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '/';
        }

        $parts = explode('/', $trimmed);
        $prefixParts = [];

        foreach ($parts as $part) {
            if (str_contains($part, ':') || str_contains($part, '{') || str_contains($part, '(')) {
                break;
            }
            $prefixParts[] = $part;
        }

        if ($prefixParts === []) {
            return '/';
        }

        return '/' . implode('/', $prefixParts);
    }

    /**
     * Gera lista de prefixos candidatos de maior especificidade para menor.
     * Ex: /api/resource1/42 => ['/api/resource1/42', '/api/resource1', '/api', '/']
     *
     * @return array<int, string>
     */
    public static function getCandidatePrefixes(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return ['/'];
        }

        $parts = explode('/', $trimmed);
        $candidates = [];
        $current = '';

        for ($i = count($parts); $i > 0; $i--) {
            $slice = array_slice($parts, 0, $i);
            $candidates[] = '/' . implode('/', $slice);
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
