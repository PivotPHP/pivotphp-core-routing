<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use PivotPHP\Routing\Router\Policy\DefaultMethodPolicy;
use PivotPHP\Routing\Router\Policy\HeadMethodPolicy;
use PivotPHP\Routing\Router\Policy\MethodPolicy;
use PivotPHP\Routing\Router\Policy\OptionsMethodPolicy;
use PivotPHP\Routing\Router\Resolution\RouteMatched;
use PivotPHP\Routing\Router\Resolution\RouteResolution;
use PivotPHP\Routing\Utils\CallableResolver;

/**
 * Router simples: registra rotas, compila padrões e casa o path.
 *
 * Sem cache, sem plugins, sem gerenciamento de memória, sem estatísticas —
 * faz uma coisa bem: rotear.
 */
class Router
{
    /**
     * Retorna a instância padrão da fachada legada.
     */
    public static function default(): self
    {
        return RouterFacade::getInstance();
    }

    public const DEFAULT_PATH = '/';

    private const CONSTRAINT_SHORTCUTS = [
        'int' => '\d+',
        'slug' => '[a-z0-9-]+',
        'alpha' => '[a-zA-Z]+',
        'alnum' => '[a-zA-Z0-9]+',
        'uuid' => '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
        'date' => '\d{4}-\d{2}-\d{2}',
        'year' => '\d{4}',
        'month' => '\d{2}',
        'day' => '\d{2}'
    ];

    private const DANGEROUS_PATTERNS = [
        '(\w+)*\w*',
        '(.+)+',
        '(a*)*',
        '(a|a)*',
        '(a+)+b'
    ];

    /**
     * Prefixo de grupo atual (para grupos aninhados).
     */
    private string $current_group_prefix = '';

    /**
     * Tabela de rotas registradas.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * Middlewares por prefixo de grupo.
     *
     * @var array<string, array<int, callable>>
     */
    private array $groupMiddlewares = [];

    /**
     * Métodos HTTP aceitos.
     *
     * @var array<int, string>
     */
    private array $httpMethodsAccepted = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

    /**
     * Índice em memória de alta performance.
     */
    private RouteIndex $routeIndex;

    /**
     * Políticas de método HTTP (Padrão Strategy).
     *
     * @var array<string, MethodPolicy>
     */
    private array $methodPolicies = [];

    public function __construct()
    {
        $this->routeIndex = new RouteIndex();
        $defaultPolicy = new DefaultMethodPolicy();
        $this->methodPolicies = [
            'GET' => $defaultPolicy,
            'POST' => $defaultPolicy,
            'PUT' => $defaultPolicy,
            'DELETE' => $defaultPolicy,
            'PATCH' => $defaultPolicy,
            'HEAD' => new HeadMethodPolicy(),
            'OPTIONS' => new OptionsMethodPolicy(),
        ];
    }

    /**
     * Registra um método HTTP adicional.
     */
    public function addHttpMethod(string $method): void
    {
        $method = strtoupper($method);
        if (!in_array($method, $this->httpMethodsAccepted, true)) {
            $this->httpMethodsAccepted[] = $method;
            if (!isset($this->methodPolicies[$method])) {
                $this->methodPolicies[$method] = new DefaultMethodPolicy();
            }
        }
    }

    /**
     * Associa middlewares a um prefixo de caminho (como `app.use(path, mw)` no Express).
     *
     * Os middlewares valem para as rotas registradas depois cujo caminho começa com o prefixo.
     * Não altera o caminho de nenhuma rota — para prefixar rotas, use group() (SPEC-053).
     */
    public function use(string $prev_path, callable ...$middlewares): void
    {
        if ($prev_path === '') {
            $prev_path = '/';
        }

        if (count($middlewares) > 0) {
            $this->groupMiddlewares[$prev_path] = array_values($middlewares);
        }
    }

    /**
     * Registra um grupo de rotas (com suporte a grupos aninhados).
     *
     * @param array<int, callable> $middlewares
     */
    public function group(
        string $prefix,
        callable $callback,
        array $middlewares = []
    ): void {
        $prefix = self::normalizePrefix($prefix);

        $previousPrefix = $this->current_group_prefix;
        if ($previousPrefix !== '' && $previousPrefix !== '/') {
            $prefix = $previousPrefix . $prefix;
        }

        $parentMiddlewares = $this->groupMiddlewares[$previousPrefix] ?? [];
        $allMiddlewares = array_merge($parentMiddlewares, $middlewares);

        $this->current_group_prefix = $prefix;

        $arity = (new ReflectionFunction(Closure::fromCallable($callback)))->getNumberOfParameters();

        if ($arity === 0) {
            if (count($allMiddlewares) > 0) {
                $this->groupMiddlewares[$prefix] = $allMiddlewares;
            }

            call_user_func($callback);
        } else {
            $groupRouter = new RouterInstance($prefix, $allMiddlewares);
            call_user_func($callback, $groupRouter);

            $routes = $groupRouter->getRoutes();
            foreach ($routes as $route) {
                $method = is_string($route['method'] ?? null) ? $route['method'] : 'GET';
                $path = is_string($route['path'] ?? null) ? $route['path'] : self::DEFAULT_PATH;
                /** @var callable|array{0: string, 1: string} $handler */
                $handler = $route['handler'];
                /** @var array<string, mixed> $metadata */
                $metadata = $route['metadata'] ?? [];
                /** @var array<int, callable> $routeMiddlewares */
                $routeMiddlewares = $route['middlewares'] ?? [];

                $this->add($method, $path, $handler, $metadata, ...$routeMiddlewares);
            }
        }

        $this->current_group_prefix = $previousPrefix;
    }

    /**
     * Adiciona uma nova rota.
     *
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function add(
        string $method,
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        if ($path === '') {
            $path = self::DEFAULT_PATH;
        }
        if (!in_array(strtoupper($method), $this->httpMethodsAccepted, true)) {
            throw new InvalidArgumentException("Method {$method} is not supported");
        }
        $method = strtoupper($method);

        // Parâmetros opcionais (:param?) não são suportados (SPEC-026).
        if (str_contains($path, '?')) {
            throw new InvalidArgumentException(
                "Optional route parameters are not supported (found '?' in '{$path}')"
            );
        }

        $resolvedHandler = CallableResolver::resolve($handler);

        foreach ($middlewares as $mw) {
            if (!is_callable($mw)) {
                throw new InvalidArgumentException('Middleware must be callable');
            }
        }

        $path = $this->optimizePathProcessing($path);
        $compiled = self::compilePattern($path);

        $route = [
            'method' => $method,
            'path' => $path,
            'middlewares' => array_merge($this->getGroupMiddlewaresForPath($path), $middlewares),
            'handler' => $resolvedHandler,
            'metadata' => self::sanitizeForJson($metadata),
            'pattern' => $compiled['pattern'],
            'parameters' => $compiled['parameters'],
            'has_parameters' => count($compiled['parameters']) > 0
        ];

        $this->routes[] = $route;
        $this->routeIndex->addRoute($route);
    }

    /**
     * Resolve o método e caminho retornando um resultado tipado (SPEC-114).
     */
    public function resolve(string $method, ?string $path = null): RouteResolution
    {
        $method = strtoupper($method);
        if ($path === null) {
            $path = self::DEFAULT_PATH;
        }

        $normalizedPath = RouteIndex::normalizePathForMatching($path);
        $policy = $this->methodPolicies[$method] ?? new DefaultMethodPolicy();

        return $policy->resolve($method, $normalizedPath, $path, $this->routeIndex);
    }

    /**
     * Identifica a rota que casa com o método e o path.
     *
     * @deprecated desde a 3.1.0; use resolve() que retorna RouteResolution tipado.
     *
     * @return array<string, mixed>|null
     */
    public function identify(string $method, ?string $path = null): ?array
    {
        $resolution = $this->resolve($method, $path);

        if ($resolution instanceof RouteMatched) {
            return $resolution->toArray();
        }

        return null;
    }

    /**
     * Métodos HTTP com ao menos uma rota casando com o path (para 405 com `Allow`) — SPEC-072.
     *
     * @deprecated desde a 3.1.0; use resolve() que já calcula os métodos permitidos em O(1).
     *
     * @return array<int, string>
     */
    public function allowedMethods(string $path): array
    {
        $normalizedPath = RouteIndex::normalizePathForMatching($path);
        return $this->routeIndex->getAllowedMethodsForPath($normalizedPath, $path);
    }



    /**
     * Remove closures, objetos e recursos de arrays recursivamente.
     */
    private static function sanitizeForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $out[$k] = self::sanitizeForJson($v);
                } elseif (is_scalar($v) || is_null($v)) {
                    $out[$k] = $v;
                } elseif (is_object($v)) {
                    $out[$k] = $v instanceof \stdClass
                        ? self::sanitizeForJson((array) $v)
                        : '[object]';
                } elseif (is_resource($v)) {
                    $out[$k] = '[resource]';
                } else {
                    $out[$k] = '[unserializable]';
                }
            }

            return $out;
        }

        if (is_scalar($value) || is_null($value)) {
            return $value;
        }
        if (is_object($value)) {
            return $value instanceof \stdClass ? self::sanitizeForJson((array) $value) : '[object]';
        }
        if (is_resource($value)) {
            return '[resource]';
        }

        return '[unserializable]';
    }



    /**
     * Normaliza um prefixo de grupo.
     */
    private static function normalizePrefix(string $prefix): string
    {
        if ($prefix === '' || $prefix === '/') {
            return '/';
        }

        $prefix = '/' . trim($prefix, '/');
        $normalized = preg_replace('/\/+/', '/', $prefix);

        return $normalized !== null ? $normalized : $prefix;
    }

    /**
     * Aplica o prefixo de grupo e normaliza o path.
     */
    private function optimizePathProcessing(string $path): string
    {
        if ($this->current_group_prefix !== '' && $this->current_group_prefix !== '/') {
            if (!str_starts_with($path, $this->current_group_prefix)) {
                $path = $this->current_group_prefix . $path;
                if (str_contains($path, '//')) {
                    $normalizedPath = preg_replace('/\/+/', '/', $path);
                    $path = $normalizedPath !== null ? $normalizedPath : $path;
                }
            }
        }

        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Obtém os middlewares de grupo aplicáveis a um path.
     *
     * @return array<int, callable>
     */
    private function getGroupMiddlewaresForPath(string $path): array
    {
        if (count($this->groupMiddlewares) === 0) {
            return [];
        }

        $groupMiddlewares = [];
        foreach ($this->groupMiddlewares as $prefix => $groupMws) {
            if ($path !== '' && str_starts_with($path, $prefix)) {
                $groupMiddlewares = array_merge($groupMiddlewares, $groupMws);
            }
        }

        return $groupMiddlewares;
    }

    // ======================================================================
    // Compilação de padrões (essencial)
    // ======================================================================

    /**
     * Compila um path em regex + lista de parâmetros.
     *
     * @return array{pattern: string|null, parameters: array<int, array<string, mixed>>}
     */
    public static function compilePattern(string $path): array
    {
        if (self::isStaticRoute($path)) {
            return ['pattern' => null, 'parameters' => []];
        }

        $pattern = $path;
        $parameters = [];
        $position = 0;

        $pattern = self::processRegexBlocks($pattern, $parameters, $position);
        $pattern = self::processBraceParameters($pattern, $parameters, $position);
        $pattern = self::processNamedParameters($pattern, $parameters, $position);

        $compiledPattern = self::finalizePattern($pattern);

        return ['pattern' => $compiledPattern, 'parameters' => $parameters];
    }

    public static function isStaticRoute(string $path): bool
    {
        return strpos($path, ':') === false && strpos($path, '{') === false;
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processRegexBlocks(
        ?string $pattern,
        array &$parameters,
        int &$position
    ): ?string {
        if ($pattern === null) {
            return '';
        }

        return preg_replace_callback(
            '/\{([^{}]+(?:\{[^{}]*\}[^{}]*)*)\}/',
            function ($matches) use (&$position, &$parameters) {
                return self::processRegexBlock($matches[1], $parameters, $position);
            },
            $pattern
        );
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processRegexBlock(
        string $content,
        array &$parameters,
        int &$position
    ): string {
        if (strpos($content, '^') === false && strpos($content, '(') === false) {
            return '{' . $content . '}';
        }

        $regex = self::removeRegexAnchors($content);

        $groupCount = self::countCaptureGroups($regex);
        self::registerAnonymousParameters($parameters, $position, $regex, $groupCount);

        $position += $groupCount;

        return $regex;
    }

    private static function removeRegexAnchors(string $regex): string
    {
        if ($regex !== '' && $regex[0] === '^') {
            $regex = substr($regex, 1);
        }

        if ($regex !== '' && substr($regex, -1) === '$') {
            if (preg_match('/\.[a-z]{2,4}\)?\$/', $regex) === 0) {
                $regex = substr($regex, 0, -1);
            }
        }

        return $regex;
    }

    private static function countCaptureGroups(string $regex): int
    {
        preg_match_all('/\([^?]/', $regex, $groups);

        return count($groups[0]);
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function registerAnonymousParameters(
        array &$parameters,
        int $position,
        string $regex,
        int $count
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $parameters[] = [
                'name' => '_anonymous_' . ($position + $i),
                'position' => $position + $i,
                'constraint' => $regex,
                'type' => 'anonymous'
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processBraceParameters(
        ?string $pattern,
        array &$parameters,
        int &$position
    ): ?string {
        if ($pattern === null) {
            return '';
        }

        return preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?:<([^>]+)>)?\}/',
            function ($matches) use (&$parameters, &$position) {
                return self::processNamedParameter($matches, $parameters, $position);
            },
            $pattern
        );
    }

    /**
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processNamedParameters(
        ?string $pattern,
        array &$parameters,
        int &$position
    ): ?string {
        if ($pattern === null) {
            return '';
        }

        return preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)(?:<([^>]+)>)?/',
            function ($matches) use (&$parameters, &$position) {
                return self::processNamedParameter($matches, $parameters, $position);
            },
            $pattern
        );
    }

    /**
     * @param array<int, string> $matches
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processNamedParameter(
        array $matches,
        array &$parameters,
        int &$position
    ): string {
        $paramName = $matches[1];
        $constraint = $matches[2] ?? '[^/]+';

        $constraint = self::resolveConstraintShortcut($constraint);

        if (!self::isRegexSafe($constraint)) {
            throw new InvalidArgumentException(
                "Unsafe regex pattern detected in route parameter '{$paramName}': {$constraint}"
            );
        }

        $parameters[] = [
            'name' => $paramName,
            'position' => $position++,
            'constraint' => $constraint
        ];

        return '(' . $constraint . ')';
    }

    private static function finalizePattern(?string $pattern): string
    {
        if ($pattern === null) {
            $pattern = '';
        }

        $pattern = self::escapeDots($pattern);

        if ($pattern !== '' && $pattern !== null) {
            $normalizedPattern = preg_replace('#/+#', '/', $pattern);
            $pattern = $normalizedPattern !== null ? $normalizedPattern : $pattern;
        }

        $pattern = rtrim($pattern ?? '', '/');

        return '#^' . $pattern . '/?$#';
    }

    private static function escapeDots(?string $pattern): ?string
    {
        if ($pattern === null) {
            return null;
        }

        return preg_replace_callback(
            '/(\\.)(?![^(]*\\))/',
            function ($matches) {
                return '\\' . $matches[1];
            },
            $pattern
        );
    }

    private static function resolveConstraintShortcut(string $constraint): string
    {
        return self::CONSTRAINT_SHORTCUTS[$constraint] ?? $constraint;
    }

    private static function isRegexSafe(string $pattern): bool
    {
        if (strlen($pattern) > 200) {
            return false;
        }

        foreach (self::DANGEROUS_PATTERNS as $dangerous) {
            if (strpos($pattern, $dangerous) !== false) {
                return false;
            }
        }

        if (preg_match('/\([^)]*[\*\+]\)[*+]/', $pattern) === 1) {
            return false;
        }

        if (preg_match('/\([^)]*\|[^)]*\)[\*\+]/', $pattern) === 1 && substr_count($pattern, '|') > 5) {
            return false;
        }

        if (substr_count($pattern, '|') > 10) {
            return false;
        }

        try {
            @preg_match('#' . $pattern . '#', '');

            return preg_last_error() === PREG_NO_ERROR;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ======================================================================
    // Verbos HTTP
    // ======================================================================

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function get(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('GET', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function post(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('POST', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function put(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('PUT', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function delete(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('DELETE', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function patch(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('PATCH', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function options(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('OPTIONS', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<string, mixed> $metadata
     */
    public function head(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        $this->add('HEAD', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * Registra múltiplas rotas para os mesmos handlers.
     *
     * @param array<int, string> $methods
     * @param string        $path
     * @param callable|array<int, string|object> $handler
     * @param array<string, mixed> $metadata
     */
    public function match(
        array $methods,
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        foreach ($methods as $method) {
            $this->add($method, $path, $handler, $metadata, ...$middlewares);
        }
    }

    /**
     * @param callable|array<int, string|object> $handler
     * @param array<string, mixed> $metadata
     */
    public function any(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        foreach ($this->httpMethodsAccepted as $method) {
            $this->add($method, $path, $handler, $metadata, ...$middlewares);
        }
    }

    /**
     * @return array<int, string>
     */
    public function getHttpMethodsAccepted(): array
    {
        return $this->httpMethodsAccepted;
    }

    /**
     * Retorna as rotas registradas.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Limpa todas as rotas e o estado de grupo.
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->groupMiddlewares = [];
        $this->current_group_prefix = '';
    }

    /**
     * Converte a tabela de rotas em string legível.
     */
    public function toString(): string
    {
        $output = '';
        foreach ($this->routes as $route) {
            $method = is_string($route['method']) ? $route['method'] : 'UNKNOWN';
            $path = is_string($route['path']) ? $route['path'] : '/';
            $handlerType = is_callable($route['handler']) ? 'Callable' : 'Not Callable';

            $output .= sprintf(
                "%s %s => %s\n",
                $method,
                $path,
                $handlerType
            );
        }

        return $output;
    }

    /**
     * Despacho dinâmico de métodos HTTP customizados na instância.
     *
     * @param array<int, mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        if (in_array(strtoupper($method), $this->httpMethodsAccepted, true)) {
            $path = array_shift($args);
            if (!is_string($path)) {
                throw new InvalidArgumentException('Route path must be a string');
            }
            /** @phpstan-ignore-next-line Dynamic dispatch: args are validated by add(). */
            $this->add(strtoupper($method), $path, ...$args);

            return null;
        }

        throw new BadMethodCallException("Method {$method} does not exist in " . static::class);
    }
}
