<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use PivotPHP\Routing\Utils\CallableResolver;

/**
 * Router simples: registra rotas, compila padrões e casa o path.
 *
 * Sem cache, sem plugins, sem gerenciamento de memória, sem estatísticas —
 * faz uma coisa bem: rotear.
 */
class Router
{
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
    private static string $current_group_prefix = '';

    /**
     * Tabela de rotas registradas.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $routes = [];

    /**
     * Middlewares por prefixo de grupo.
     *
     * @var array<string, array<int, callable>>
     */
    private static array $groupMiddlewares = [];

    /**
     * Métodos HTTP aceitos.
     *
     * @var array<int, string>
     */
    private static array $httpMethodsAccepted = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

    /**
     * Registra um método HTTP adicional.
     */
    public static function addHttpMethod(string $method): void
    {
        $method = strtoupper($method);
        if (!in_array($method, self::$httpMethodsAccepted, true)) {
            self::$httpMethodsAccepted[] = $method;
        }
    }

    /**
     * Define um prefixo/base para rotas agrupadas OU registra middlewares para um grupo.
     */
    public static function use(string $prev_path, callable ...$middlewares): void
    {
        if ($prev_path === '') {
            $prev_path = '/';
        }
        self::$current_group_prefix = $prev_path;

        if (count($middlewares) > 0) {
            self::$groupMiddlewares[$prev_path] = array_values($middlewares);
        }
    }

    /**
     * Registra um grupo de rotas (com suporte a grupos aninhados).
     *
     * @param array<int, callable> $middlewares
     */
    public static function group(
        string $prefix,
        callable $callback,
        array $middlewares = []
    ): void {
        $prefix = self::normalizePrefix($prefix);

        $previousPrefix = self::$current_group_prefix;
        if ($previousPrefix !== '' && $previousPrefix !== '/') {
            $prefix = $previousPrefix . $prefix;
        }

        $parentMiddlewares = self::$groupMiddlewares[$previousPrefix] ?? [];
        $allMiddlewares = array_merge($parentMiddlewares, $middlewares);

        self::$current_group_prefix = $prefix;

        $arity = (new ReflectionFunction(Closure::fromCallable($callback)))->getNumberOfParameters();

        if ($arity === 0) {
            if (count($allMiddlewares) > 0) {
                self::$groupMiddlewares[$prefix] = $allMiddlewares;
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

                self::add($method, $path, $handler, $metadata, ...$routeMiddlewares);
            }
        }

        self::$current_group_prefix = $previousPrefix;
    }

    /**
     * Adiciona uma nova rota.
     *
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function add(
        string $method,
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        if ($path === '') {
            $path = self::DEFAULT_PATH;
        }
        if (!in_array(strtoupper($method), self::$httpMethodsAccepted, true)) {
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

        $path = self::optimizePathProcessing($path);
        $compiled = self::compilePattern($path);

        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'middlewares' => array_merge(self::getGroupMiddlewaresForPath($path), $middlewares),
            'handler' => $resolvedHandler,
            'metadata' => self::sanitizeForJson($metadata),
            'pattern' => $compiled['pattern'],
            'parameters' => $compiled['parameters'],
            'has_parameters' => count($compiled['parameters']) > 0
        ];
    }

    /**
     * Identifica a rota que casa com o método e o path.
     *
     * @return array<string, mixed>|null
     */
    public static function identify(string $method, ?string $path = null): ?array
    {
        $method = strtoupper($method);

        if ($path === null) {
            $path = self::DEFAULT_PATH;
        }

        $normalizedPath = self::normalizePathForMatching($path);

        // 1. Match estático exato (com normalização de trailing slash).
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $routePath = is_string($route['path']) ? $route['path'] : self::DEFAULT_PATH;
            if (self::normalizePathForMatching($routePath) === $normalizedPath) {
                return $route;
            }
        }

        // 2. Match dinâmico (parâmetros).
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $matched = self::matchRoutePattern($route, $path);
            if ($matched !== null) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * Normaliza um path para comparação (remove trailing slash, exceto na raiz).
     */
    private static function normalizePathForMatching(string $path): string
    {
        if ($path !== '/' && $path !== '' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
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
     * Extrai parâmetros correspondentes de uma rota.
     *
     * @param array<int, array<string, mixed>> $parameters
     * @param array<int, string> $matches
     * @return array<string, string>
     */
    private static function extractMatchedParameters(array $parameters, array $matches): array
    {
        $params = [];

        for ($i = 1; $i < count($matches); $i++) {
            $paramInfo = $parameters[$i - 1] ?? null;
            if (is_array($paramInfo) && isset($paramInfo['name']) && is_string($paramInfo['name'])) {
                $params[$paramInfo['name']] = $matches[$i];
            }
        }

        return $params;
    }

    /**
     * Tenta casar uma rota (via pattern) contra um path.
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
                $route['matched_params'] = self::extractMatchedParameters($parameters, $matches);
            }

            return $route;
        }

        return null;
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
    private static function optimizePathProcessing(string $path): string
    {
        if (self::$current_group_prefix !== '' && self::$current_group_prefix !== '/') {
            if (!str_starts_with($path, self::$current_group_prefix)) {
                $path = self::$current_group_prefix . $path;
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
    private static function getGroupMiddlewaresForPath(string $path): array
    {
        if (count(self::$groupMiddlewares) === 0) {
            return [];
        }

        $groupMiddlewares = [];
        foreach (self::$groupMiddlewares as $prefix => $groupMws) {
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
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function get(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('GET', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function post(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('POST', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function put(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('PUT', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function delete(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('DELETE', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function patch(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('PATCH', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function options(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('OPTIONS', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function head(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        self::add('HEAD', $path, $handler, $metadata, ...$middlewares);
    }

    /**
     * @param callable|array{0: string, 1: string} $handler
     * @param array<string, mixed> $metadata
     */
    public static function any(
        string $path,
        callable|array $handler,
        array $metadata = [],
        callable ...$middlewares
    ): void {
        foreach (self::$httpMethodsAccepted as $method) {
            self::add($method, $path, $handler, $metadata, ...$middlewares);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function getHttpMethodsAccepted(): array
    {
        return self::$httpMethodsAccepted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Limpa todas as rotas e o estado de grupo.
     */
    public static function clear(): void
    {
        self::$routes = [];
        self::$groupMiddlewares = [];
        self::$current_group_prefix = '';
    }

    /**
     * Converte a tabela de rotas em string legível.
     */
    public static function toString(): string
    {
        $output = '';
        foreach (self::$routes as $route) {
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
     * @param array<int, mixed> $args
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        if (in_array(strtoupper($method), self::$httpMethodsAccepted, true)) {
            $path = array_shift($args);
            if (!is_string($path)) {
                throw new InvalidArgumentException('Route path must be a string');
            }
            /** @phpstan-ignore-next-line Dynamic dispatch: args are validated by add(). */
            self::add(strtoupper($method), $path, ...$args);

            return null;
        }

        if (method_exists(self::class, $method)) {
            /** @phpstan-ignore-next-line Dynamic method dispatch. */
            return self::{$method}(...$args);
        }

        throw new BadMethodCallException("Method {$method} does not exist in " . self::class);
    }
}
