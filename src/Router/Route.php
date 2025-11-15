<?php

namespace PivotPHP\Routing\Router;

use InvalidArgumentException;

/**
 * Classe Route representa uma rota individual.
 */
class Route
{
    private string $method;
    private string $path;
    private string $pattern;
    /** @var array<string, mixed> */
    private array $parameters = [];
    /** @var array<int, string> */
    private array $parameterNames = [];
    /** @var array<callable> */
    private array $middlewares = [];
    /**
     * @var callable
     */
    private $handler;
    /** @var array<string, mixed> */
    private array $metadata = [];
    private ?string $name = null;

    /**
     * @param string               $method
     * @param string               $path
     * @param callable             $handler
     * @param array<callable>      $middlewares
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $method,
        string $path,
        $handler,
        array $middlewares = [],
        array $metadata = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->middlewares = $middlewares;
        $this->metadata = $metadata;
        $this->compilePattern();
    }

    /**
     * Compila o padrão da rota para regex.
     *
     * @return void
     */
    private function compilePattern(): void
    {
        $pattern = $this->path;

        // Encontra parâmetros na rota (:param)
        preg_match_all('/\/:([^\/]+)/', $pattern, $matches);
        $this->parameterNames = $matches[1];

        // Converte parâmetros para regex
        $pattern = preg_replace('/\/:([^\/]+)/', '/([^/]+)', $pattern);

        // Permite barra final opcional
        $pattern = rtrim($pattern ?? '', '/');
        $this->pattern = '#^' . $pattern . '/?$#';
    }

    /**
     * Verifica se a rota corresponde ao caminho dado.
     *
     * @param  string $path
     * @return bool
     */
    public function matches(string $path): bool
    {
        if ($this->path === '/') {
            return $path === '/';
        }

        return preg_match($this->pattern, $path) === 1;
    }

    /**
     * Extrai os parâmetros do caminho.
     *
     * @param  string $path
     * @return array<string, string|int>
     */
    public function extractParameters(string $path): array
    {
        if (count($this->parameters) === 0) {
            return [];
        }

        $matchResult = preg_match($this->pattern, $path, $matches);
        if ($matchResult !== 1 || count($matches) === 0) {
            return [];
        }

        array_shift($matches); // Remove o match completo

        $parameters = [];
        for ($i = 0; $i < count($this->parameterNames) && $i < count($matches); $i++) {
            $value = $matches[$i];
            if (is_numeric($value)) {
                $value = (int)$value;
            }
            $parameterName = $this->parameterNames[$i] ?? "param{$i}";
            $parameters[$parameterName] = $value;
        }

        return $parameters;
    }

    /**
     * Verifica se a rota tem parâmetros.
     *
     * @return bool
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Obtém o método HTTP.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtém o caminho da rota.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Obtém o handler da rota.
     *
     * @return callable
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Obtém os middlewares da rota.
     *
     * @return array<callable>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Obtém os metadados da rota.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Define o nome da rota.
     *
     * @param  string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Obtém o nome da rota.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Adiciona um middleware à rota.
     *
     * @param  callable $middleware
     * @return $this
     */
    public function middleware($middleware): self
    {
        if (!is_callable($middleware)) {
            throw new InvalidArgumentException('Middleware must be callable');
        }

        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Define metadados para a rota.
     *
     * @param  array<string, mixed> $metadata
     * @return $this
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Gera uma URL para a rota com os parâmetros dados.
     *
     * @param  array<string, string|int> $parameters
     * @return string
     */
    public function url(array $parameters = []): string
    {
        $url = $this->path;

        foreach ($parameters as $key => $value) {
            $url = str_replace(':' . $key, (string)$value, $url);
        }

        return $url;
    }

    /**
     * Converte a rota para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'parameters' => $this->parameters,
            'metadata' => $this->metadata,
            'name' => $this->name,
            'handler' => 'Callable'
        ];
    }
}
