<?php

namespace PivotPHP\Routing\Router;

use PivotPHP\Routing\Utils\SerializationCache;

/**
 * Cache para rotas compiladas para melhorar performance
 */
class RouteCache
{
    /**
     * Cache de rotas compiladas
     * @var array<string, array<string, mixed>>
     */
    private static array $compiledRoutes = [];

    /**
     * Cache de mapeamentos de parâmetros
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $parameterMappings = [];

    /**
     * Cache de patterns compilados
     * @var array<string, string>
     */
    private static array $compiledPatterns = [];

    /**
     * Cache de patterns pré-compilados para evitar recompilação
     * @var array<string, array<string, mixed>>
     */
    private static array $fastParameterCache = [];

    /**
     * Cache de rotas por tipo (com ou sem parâmetros)
     * @var array<string, array<int|string, array<string, mixed>>>
     */
    private static array $routeTypeCache = [
        'static' => [],
        'dynamic' => []
    ];

    /**
     * Estatísticas de cache
     * @var array<string, int>
     */
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'compilations' => 0
    ];

    /**
     * Cache para cálculos de uso de memória
     * @var array<string, mixed>|null
     */
    private static ?array $memoryUsageCache = null;

    /**
     * Hash dos dados para invalidação do cache de memória
     */
    private static ?string $lastDataHash = null;

    /**
     * Mapeamento de shortcuts para constraints regex
     */
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

    /**
     * Padrões regex perigosos para detecção de ReDoS
     */
    private const DANGEROUS_PATTERNS = [
        '(\w+)*\w*',
        '(.+)+',
        '(a*)*',
        '(a|a)*',
        '(a+)+b'
    ];

    /**
     * Obtém uma rota do cache
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        if (isset(self::$compiledRoutes[$key])) {
            self::$stats['hits']++;
            return self::$compiledRoutes[$key];
        }

        self::$stats['misses']++;
        return null;
    }

    /**
     * Armazena uma rota no cache
     * @param array<string, mixed> $route
     */
    public static function set(string $key, array $route): void
    {
        self::$compiledRoutes[$key] = $route;
        self::invalidateMemoryCache();
    }

    /**
     * Gera chave de cache para método e caminho
     */
    public static function generateKey(string $method, string $path): string
    {
        return $method . '::' . $path;
    }

    /**
     * Obtém pattern compilado do cache
     */
    public static function getPattern(string $path): ?string
    {
        return self::$compiledPatterns[$path] ?? null;
    }

    /**
     * Armazena pattern compilado no cache
     */
    public static function setPattern(string $path, string $pattern): void
    {
        self::$compiledPatterns[$path] = $pattern;
        self::$stats['compilations']++;
        self::invalidateMemoryCache();
    }

    /**
     * Obtém parâmetros do cache
     * @return array<int, array<string, mixed>>|null
     */
    public static function getParameters(string $path): ?array
    {
        return self::$parameterMappings[$path] ?? null;
    }

    /**
     * Armazena parâmetros no cache
     * @param array<int, array<string, mixed>> $parameters
     */
    public static function setParameters(string $path, array $parameters): void
    {
        self::$parameterMappings[$path] = $parameters;
        self::invalidateMemoryCache();
    }

    /**
     * Compila pattern de rota para regex otimizada (versão melhorada com suporte a constraints)
     * @return array{pattern: string|null, parameters: array<int, array<string, mixed>>}
     */
    public static function compilePattern(string $path): array
    {
        // Try to get from cache first
        $cached = self::getFromCache($path);
        if ($cached !== null) {
            return $cached;
        }

        // Check if it's a static route (optimization)
        if (self::isStaticPath($path)) {
            return self::cacheStaticRoute($path);
        }

        // Compile dynamic route
        $pattern = $path;
        $parameters = [];
        $position = 0;

        // Process regex blocks
        $pattern = self::processRegexBlocks($pattern, $parameters, $position);

        // Process named parameters
        $pattern = self::processNamedParameters($pattern, $parameters, $position);

        // Escape dots and finalize pattern
        $compiledPattern = self::finalizePattern($pattern);

        // Cache and return result
        return self::cacheDynamicRoute($path, $compiledPattern, $parameters);
    }

    /**
     * Get compiled pattern from cache if available
     * @return array{pattern: string|null, parameters: array<int, array<string, mixed>>}|null
     */
    private static function getFromCache(string $path): ?array
    {
        $cached = self::$fastParameterCache[$path] ?? null;
        if ($cached !== null) {
            /** @var array{pattern: string|null, parameters: array<int, array<string, mixed>>} */
            return $cached;
        }

        $cachedPattern = self::getPattern($path);
        $cachedParams = self::getParameters($path);

        if ($cachedPattern !== null && $cachedParams !== null) {
            /** @var array{pattern: string|null, parameters: array<int, array<string, mixed>>} $result */
            $result = [
                'pattern' => $cachedPattern,
                'parameters' => $cachedParams
            ];
            self::$fastParameterCache[$path] = $result;
            return $result;
        }

        return null;
    }

    /**
     * Check if the path is static (no parameters)
     */
    private static function isStaticPath(string $path): bool
    {
        return strpos($path, ':') === false && strpos($path, '{') === false;
    }

    /**
     * Cache static route data
     * @return array{pattern: string|null, parameters: array<int, array<string, mixed>>}
     */
    private static function cacheStaticRoute(string $path): array
    {
        /** @var array{pattern: string|null, parameters: array<int, array<string, mixed>>} $result */
        $result = [
            'pattern' => null, // Static routes don't need regex
            'parameters' => []
        ];
        self::$fastParameterCache[$path] = $result;
        self::$routeTypeCache['static'][$path] = ['cached' => true];
        return $result;
    }

    /**
     * Process regex blocks like {^pattern$}
     *
     * This method handles brace-delimited regex blocks in route patterns.
     * The regex pattern `/\{([^{}]+(?:\{[^{}]*\}[^{}]*)*)\}/` works as follows:
     *
     * - `\{` - Match opening brace literally
     * - `(` - Start capture group
     * - `[^{}]+` - Match one or more non-brace characters (main content)
     * - `(?:` - Start non-capturing group for nested braces
     * - `\{[^{}]*\}` - Match a complete inner brace pair with non-brace content
     * - `[^{}]*` - Followed by any non-brace characters
     * - `)*` - The non-capturing group can repeat zero or more times
     * - `)` - End capture group
     * - `\}` - Match closing brace literally
     *
     * Supported patterns:
     * - Simple: `{^v(\d+)$}` → Matches version numbers
     * - With alternation: `{^(images|videos)$}` → Matches specific values
     * - File extensions: `{^(.+)\.(pdf|doc|txt)$}` → Matches files with extensions
     *
     * Limitations:
     * - Does not handle deeply nested braces (more than 2 levels)
     * - Assumes balanced braces within the pattern
     * - Best suited for simple regex patterns with basic grouping
     *
     * @param string|null $pattern The route pattern containing regex blocks
     * @param array<int, array<string, mixed>> $parameters Array to store parameter information
     * @param int $position Current position counter for parameters
     * @return string|null The processed pattern with regex blocks expanded
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
     * Process a single regex block
     *
     * This method processes the content inside a regex block after it has been
     * extracted by processRegexBlocks. It handles:
     * - Anchor removal (^ and $ characters)
     * - Capture group detection and parameter registration
     * - Position tracking for parameter extraction
     *
     * Alternative simpler approach for future consideration:
     * ```php
     * // For simple use cases, consider using a more restrictive pattern:
     * // '/\{([^{}]+)\}/' - Matches only non-nested braces
     * // This would be more robust but less flexible
     * ```
     *
     * @param string $content The content inside the braces
     * @param array<int, array<string, mixed>> $parameters Parameter array to update
     * @param int $position Current position for parameter tracking
     * @return string The processed regex content
     */
    private static function processRegexBlock(
        string $content,
        array &$parameters,
        int &$position
    ): string {
        // Only process if it's a full regex block (contains ^ or capture groups)
        if (strpos($content, '^') === false && strpos($content, '(') === false) {
            return '{' . $content . '}'; // Return unchanged
        }

        // Remove anchors
        $regex = self::removeRegexAnchors($content);

        // Count and register capture groups
        $groupCount = self::countCaptureGroups($regex);
        self::registerAnonymousParameters($parameters, $position, $regex, $groupCount);

        $position += $groupCount;
        return $regex;
    }

    /**
     * Remove regex anchors appropriately
     */
    private static function removeRegexAnchors(string $regex): string
    {
        // Remove leading ^ only if it's at the very beginning
        if ($regex !== '' && $regex[0] === '^') {
            $regex = substr($regex, 1);
        }

        // Remove trailing $ only if it doesn't appear to be part of regex logic
        if ($regex !== '' && substr($regex, -1) === '$') {
            // Don't remove $ if pattern contains file extensions like (.+\.json)
            if (!preg_match('/\.[a-z]{2,4}\)?\$/', $regex)) {
                $regex = substr($regex, 0, -1);
            }
        }

        return $regex;
    }

    /**
     * Count capture groups in regex
     */
    private static function countCaptureGroups(string $regex): int
    {
        preg_match_all('/\([^?]/', $regex, $groups);
        return count($groups[0]);
    }

    /**
     * Register anonymous parameters from regex blocks
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
     * Process named parameters like :param<constraint>
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
     * Process a single named parameter
     * @param array<int, string> $matches
     * @param array<int, array<string, mixed>> $parameters
     */
    private static function processNamedParameter(
        array $matches,
        array &$parameters,
        int &$position
    ): string {
        $paramName = $matches[1];
        $constraint = $matches[2] ?? '[^/]+'; // Default constraint

        // Resolve constraint shortcuts
        $constraint = self::resolveConstraintShortcut($constraint);

        // Validate regex safety
        if (!self::isRegexSafe($constraint)) {
            throw new \InvalidArgumentException(
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

    /**
     * Finalize the pattern for use
     */
    private static function finalizePattern(?string $pattern): string
    {
        if ($pattern === null) {
            $pattern = '';
        }

        // Escape dots outside of capture groups
        $pattern = self::escapeDots($pattern);

        // Remove duplicate slashes
        if ($pattern !== '' && $pattern !== null) {
            $normalizedPattern = preg_replace('#/+#', '/', $pattern);
            $pattern = $normalizedPattern !== null ? $normalizedPattern : $pattern;
        }

        // Trim trailing slash and add regex delimiters
        $pattern = rtrim($pattern ?? '', '/');
        return '#^' . $pattern . '/?$#';
    }

    /**
     * Escape dots that are outside capture groups
     */
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

    /**
     * Cache dynamic route data
     * @param array<int, array<string, mixed>> $parameters
     * @return array{pattern: string|null, parameters: array<int, array<string, mixed>>}
     */
    private static function cacheDynamicRoute(
        string $path,
        string $compiledPattern,
        array $parameters
    ): array {
        /** @var array{pattern: string|null, parameters: array<int, array<string, mixed>>} $result */
        $result = [
            'pattern' => $compiledPattern,
            'parameters' => $parameters
        ];

        // Cache in multiple places for fast access
        self::setPattern($path, $compiledPattern);
        self::setParameters($path, $parameters);
        self::$fastParameterCache[$path] = $result;
        self::$routeTypeCache['dynamic'][$path] = ['cached' => true];

        return $result;
    }

    /**
     * Resolve shortcuts de constraints para regex completo
     */
    private static function resolveConstraintShortcut(string $constraint): string
    {
        return self::CONSTRAINT_SHORTCUTS[$constraint] ?? $constraint;
    }

    /**
     * Verifica se um pattern regex é seguro contra ReDoS
     */
    private static function isRegexSafe(string $pattern): bool
    {
        // Verifica comprimento máximo
        if (strlen($pattern) > 200) {
            return false;
        }

        // Verifica padrões perigosos conhecidos
        foreach (self::DANGEROUS_PATTERNS as $dangerous) {
            if (strpos($pattern, $dangerous) !== false) {
                return false;
            }
        }

        // Verifica nested quantifiers perigosos
        // Procura por quantifiers repetidos como (x+)+ ou (x*)*
        if (preg_match('/\([^)]*[\*\+]\)[*+]/', $pattern)) {
            return false;
        }

        // Verifica backtracking excessivo
        if (preg_match('/\([^)]*\|[^)]*\)[\*\+]/', $pattern) && substr_count($pattern, '|') > 5) {
            return false;
        }

        // Verifica alternations excessivas
        if (substr_count($pattern, '|') > 10) {
            return false;
        }

        // Tenta compilar o regex para verificar se é válido
        try {
            @preg_match('#' . $pattern . '#', '');
            return preg_last_error() === PREG_NO_ERROR;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica se uma rota é estática (sem parâmetros)
     */
    public static function isStaticRoute(string $path): bool
    {
        if (isset(self::$routeTypeCache['static'][$path])) {
            return true;
        }
        if (isset(self::$routeTypeCache['dynamic'][$path])) {
            return false;
        }
        return strpos($path, ':') === false && strpos($path, '{') === false;
    }

    /**
     * Verifica se rota está em cache
     */
    public static function has(string $key): bool
    {
        return isset(self::$compiledRoutes[$key]);
    }

    /**
     * Remove uma rota do cache
     */
    public static function remove(string $key): void
    {
        unset(self::$compiledRoutes[$key]);
        self::invalidateMemoryCache();
    }

    /**
     * Limpa todo o cache
     */
    public static function clear(): void
    {
        self::$compiledRoutes = [];
        self::$parameterMappings = [];
        self::$compiledPatterns = [];
        self::$fastParameterCache = [];
        self::$routeTypeCache = [
            'static' => [],
            'dynamic' => []
        ];
        self::$stats = [
            'hits' => 0,
            'misses' => 0,
            'compilations' => 0
        ];
    }

    /**
     * Invalida o cache de uso de memória
     */
    private static function invalidateMemoryCache(): void
    {
        self::$memoryUsageCache = null;
        self::$lastDataHash = null;
    }

    /**
     * Limpa todos os caches incluindo cache de serialização
     */
    public static function clearCache(): void
    {
        self::$compiledRoutes = [];
        self::$parameterMappings = [];
        self::$compiledPatterns = [];
        self::$fastParameterCache = [];
        self::$routeTypeCache = ['static' => [], 'dynamic' => []];
        self::$stats = ['hits' => 0, 'misses' => 0, 'compilations' => 0];
        self::invalidateMemoryCache();

        // Limpa cache de serialização relacionado
        SerializationCache::clearCache();
    }

    /**
     * Obtém estatísticas do cache
     * @return array<string, mixed>
     */
    public static function getStats(): array
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? (self::$stats['hits'] / $total) * 100 : 0;

        return [
            'hits' => self::$stats['hits'],
            'misses' => self::$stats['misses'],
            'total_requests' => $total,
            'hit_rate_percentage' => round($hitRate, 2),
            'compilations' => self::$stats['compilations'],
            'cached_routes' => count(self::$compiledRoutes),
            'cached_patterns' => count(self::$compiledPatterns),
            'memory_usage' => self::getMemoryUsage()
        ];
    }

    /**
     * Calcula uso de memória do cache com cache otimizado para melhor performance
     */
    private static function getMemoryUsage(): string
    {
        // Gera hash dos dados atuais de forma mais eficiente
        $currentDataHash = md5(
            count(self::$compiledRoutes) . '|' .
            count(self::$compiledPatterns) . '|' .
            count(self::$parameterMappings) . '|' .
            serialize(array_keys(self::$compiledRoutes))
        );

        // Se o cache é válido, retorna os dados em cache
        if (self::$memoryUsageCache !== null && self::$lastDataHash === $currentDataHash) {
            $formatted = self::$memoryUsageCache['formatted'] ?? '';
            return is_string($formatted) ? $formatted : '';
        }

        // Recalcula o uso de memória usando cache de serialização otimizado
        $objects = [
            'routes' => self::$compiledRoutes,
            'patterns' => self::$compiledPatterns,
            'parameters' => self::$parameterMappings
        ];

        $cacheKeys = ['route_cache_routes', 'route_cache_patterns', 'route_cache_parameters'];
        $size = SerializationCache::getTotalSerializedSize(array_values($objects), $cacheKeys);

        $formatted = '';
        if ($size < 1024) {
            $formatted = $size . ' B';
        } elseif ($size < 1048576) {
            $formatted = round($size / 1024, 2) . ' KB';
        } else {
            $formatted = round($size / 1048576, 2) . ' MB';
        }

        // Calcula tamanhos individuais usando cache
        $routesSize = SerializationCache::getSerializedSize(self::$compiledRoutes, 'route_cache_routes');
        $patternsSize = SerializationCache::getSerializedSize(self::$compiledPatterns, 'route_cache_patterns');
        $parametersSize = SerializationCache::getSerializedSize(self::$parameterMappings, 'route_cache_parameters');

        // Armazena no cache
        self::$memoryUsageCache = [
            'raw_size' => $size,
            'formatted' => $formatted,
            'routes_memory' => $routesSize,
            'patterns_memory' => $patternsSize,
            'parameters_memory' => $parametersSize,
            'serialization_stats' => SerializationCache::getStats()
        ];
        self::$lastDataHash = $currentDataHash;

        return $formatted;
    }

    /**
     * Pré-aquece o cache com rotas conhecidas
     * @param array<int, array<string, mixed>> $routes
     */
    public static function warmup(array $routes): void
    {
        foreach ($routes as $route) {
            $method = is_string($route['method'] ?? null) ? $route['method'] : '';
            $path = is_string($route['path'] ?? null) ? $route['path'] : '';

            $key = self::generateKey($method, $path);

            // Compila pattern antecipadamente
            $compiled = self::compilePattern($path);

            $cachedRoute = array_merge(
                $route,
                [
                    'pattern' => $compiled['pattern'],
                    'parameters' => $compiled['parameters'],
                    'has_parameters' => !empty($compiled['parameters'])
                ]
            );

            self::set($key, $cachedRoute);
        }
    }

    /**
     * Obtém informações detalhadas do cache para debug
     * @return array<string, mixed>
     */
    public static function getDebugInfo(): array
    {
        // Garante que o cache de memória está atualizado
        self::getMemoryUsage();

        return [
            'cache_size' => [
                'routes' => count(self::$compiledRoutes),
                'patterns' => count(self::$compiledPatterns),
                'parameters' => count(self::$parameterMappings)
            ],
            'statistics' => self::getStats(),
            'sample_keys' => array_slice(array_keys(self::$compiledRoutes), 0, 10),
            'memory_details' => [
                'routes_memory' => self::$memoryUsageCache['routes_memory'] ?? 0,
                'patterns_memory' => self::$memoryUsageCache['patterns_memory'] ?? 0,
                'parameters_memory' => self::$memoryUsageCache['parameters_memory'] ?? 0
            ],
            'constraint_shortcuts' => self::CONSTRAINT_SHORTCUTS
        ];
    }

    /**
     * Obtém lista de shortcuts disponíveis para constraints
     * @return array<string, string>
     */
    public static function getAvailableShortcuts(): array
    {
        return self::CONSTRAINT_SHORTCUTS;
    }
}
