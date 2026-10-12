<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Resolution;

/**
 * Resultado de rota encontrada com sucesso.
 */
final class RouteMatched extends RouteResolution
{
    /**
     * @param array<string, mixed> $route Definição interna da rota
     * @param array<string, string> $params Parâmetros extraídos da rota
     * @param array<int, callable> $middlewares Middlewares da rota
     * @param array<string, mixed> $metadata Metadados da rota
     */
    public function __construct(
        private readonly array $route,
        private readonly array $params = [],
        private readonly array $middlewares = [],
        private readonly array $metadata = []
    ) {
    }

    public function isMatched(): bool
    {
        return true;
    }

    public function isMethodNotAllowed(): bool
    {
        return false;
    }

    public function isNotFound(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoute(): array
    {
        return $this->route;
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<int, callable>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMethod(): string
    {
        $method = $this->route['method'] ?? 'GET';
        return is_string($method) ? $method : 'GET';
    }

    public function getPath(): string
    {
        $path = $this->route['path'] ?? '/';
        return is_string($path) ? $path : '/';
    }

    public function getHandler(): mixed
    {
        return $this->route['handler'] ?? null;
    }

    /**
     * Converte para o array legado retornado por Router::identify().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $arr = $this->route;
        if ($this->params !== []) {
            $arr['matched_params'] = $this->params;
        }
        return $arr;
    }
}
