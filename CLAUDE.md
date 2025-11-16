# CLAUDE.md - PivotPHP Core Routing

This file provides guidance to Claude Code when working with the PivotPHP Core Routing package.

## Package Overview

**pivotphp/core-routing** is a modular, high-performance routing system extracted from pivotphp-core. It provides Express.js-inspired routing with full PSR-7/PSR-15 compliance and a powerful plugin architecture.

## Key Features

- **Express.js API**: Familiar routing patterns (get, post, put, delete, etc.)
- **High Performance**: Multi-level caching, route indexing, object pooling
- **PSR Compliance**: PSR-7 (HTTP), PSR-15 (Middleware), PSR-6/PSR-16 (Cache)
- **Plugin System**: Extensible architecture with built-in plugins
- **File Caching**: Persistent route compilation for faster startup
- **Static File Serving**: Express-style static file management
- **Modular**: Use independently or integrate with PivotPHP Core

## Project Structure

```
pivotphp-core-routing/
├── src/
│   ├── Contracts/          # Interfaces for routing components
│   │   ├── RouterInterface.php
│   │   ├── RouteInterface.php
│   │   ├── RouteCollectionInterface.php
│   │   ├── RouteMatcherInterface.php
│   │   ├── PluginInterface.php
│   │   ├── CacheStrategyInterface.php
│   │   ├── FileCacheInterface.php
│   │   └── MemoryCacheInterface.php
│   ├── Router/             # Core routing implementation
│   │   ├── Router.php
│   │   ├── Route.php
│   │   ├── RouteCollection.php
│   │   ├── RouteCache.php
│   │   ├── RouteMemoryManager.php
│   │   └── RouterInstance.php
│   ├── Cache/              # Caching strategies
│   │   ├── FileCacheStrategy.php
│   │   ├── MemoryCacheStrategy.php
│   │   └── NullCacheStrategy.php
│   ├── Static/             # Static file serving
│   │   ├── StaticFileManager.php
│   │   └── SimpleStaticFileManager.php
│   ├── Plugins/            # Plugin system
│   │   ├── AbstractPlugin.php
│   │   ├── PluginManager.php
│   │   ├── MetricsPlugin.php
│   │   ├── CachePlugin.php
│   │   └── DebugPlugin.php
│   └── Utils/              # Utilities
│       ├── CallableResolver.php
│       ├── SerializationCache.php
│       └── Arr.php
├── tests/                  # Test suites
│   ├── Router/
│   ├── Unit/
│   ├── Integration/
│   ├── Plugins/
│   └── Cache/
└── storage/cache/routes/   # Cache directory
```

## Essential Commands

### Development Workflow
```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis (PHPStan Level 9)
composer phpstan

# Code style check (PSR-12)
composer cs:check

# Auto-fix code style
composer cs:fix

# All quality checks
composer quality:check
```

### Running Specific Tests
```bash
# Run specific test file
vendor/bin/phpunit tests/Router/RouterTest.php

# Run specific test suite
vendor/bin/phpunit --testsuite=Core
vendor/bin/phpunit --testsuite=Plugins
vendor/bin/phpunit --testsuite=Cache
```

## Architecture Patterns

### Contract-Based Design
All major components are defined by interfaces in `src/Contracts/`, allowing for:
- Alternative implementations
- Easy testing with mocks
- Clear API boundaries
- Backward compatibility

### Plugin System
Plugins extend router functionality without modifying core code:

```php
use PivotPHP\Routing\Plugins\AbstractPlugin;
use PivotPHP\Routing\Contracts\RouterInterface;

class CustomPlugin extends AbstractPlugin
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        // Plugin initialization
    }
}

// Register plugin
$router->registerPlugin(new CustomPlugin());
```

### Cache Strategies
Three caching strategies available:

1. **FileCacheStrategy**: Persistent file-based caching
```php
use PivotPHP\Routing\Cache\FileCacheStrategy;

$cache = new FileCacheStrategy('/path/to/cache');
$router->setCacheStrategy($cache);
```

2. **MemoryCacheStrategy**: High-performance in-memory caching
```php
use PivotPHP\Routing\Cache\MemoryCacheStrategy;

$cache = new MemoryCacheStrategy(10 * 1024 * 1024); // 10MB limit
$router->setCacheStrategy($cache);
```

3. **NullCacheStrategy**: No-op for when caching is disabled

## Coding Standards

### Type Safety
- Strict typing enforced throughout (`declare(strict_types=1);`)
- PHPStan Level 9 compliance required
- All parameters and return types must be declared

### PSR-12 Compliance
- Follow PSR-12 coding style
- Use `composer cs:fix` to auto-format
- All code must pass `composer cs:check`

### Documentation
- All public methods require PHPDoc comments
- Include `@param`, `@return`, and `@throws` tags
- Document complex logic with inline comments

## Testing Guidelines

### Test Organization
- **Router/** - Core router functionality tests
- **Unit/** - Isolated unit tests
- **Integration/** - Component integration tests
- **Plugins/** - Plugin-specific tests
- **Cache/** - Caching strategy tests

### Test Standards
- Each test class should test one component
- Use descriptive test method names
- Include edge cases and error conditions
- Maintain >90% code coverage

### Example Test
```php
use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouterTest extends TestCase
{
    public function testBasicGetRoute(): void
    {
        Router::get('/users', function($req, $res) {
            return $res->json(['users' => []]);
        });

        $route = Router::identify('GET', '/users');
        $this->assertNotNull($route);
        $this->assertEquals('/users', $route['path']);
    }
}
```

## Performance Considerations

### Route Compilation
- Routes are compiled to regex patterns on registration
- Patterns are cached for reuse
- Use constraints for better performance: `/users/:id<\d+>`

### Memory Management
- RouteMemoryManager tracks memory usage
- Automatic garbage collection when thresholds are exceeded
- Monitor memory with `$router::getStats()`

### Caching Strategy
- File cache for production (persistent across requests)
- Memory cache for development (faster but cleared on restart)
- Null cache for testing (no overhead)

## Integration with PivotPHP Core

### Service Provider (v2.0)
```php
use PivotPHP\Core\Providers\ServiceProvider;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Cache\FileCacheStrategy;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Router::class, function($app) {
            $router = new Router();

            // Configure caching
            $cache = new FileCacheStrategy(
                $app->storagePath('cache/routes')
            );
            $router->setCacheStrategy($cache);

            return $router;
        });
    }
}
```

### Breaking Changes from v1.x
- Namespace changed from `PivotPHP\Core\Routing` to `PivotPHP\Routing\Router`
- Router implements `RouterInterface`
- Cache system now uses strategy pattern
- Plugin system added

## Built-in Plugins

### MetricsPlugin
Collects routing performance metrics:
```php
use PivotPHP\Routing\Plugins\MetricsPlugin;

$metrics = new MetricsPlugin();
$router->registerPlugin($metrics);

// Get metrics
$stats = $metrics->getMetrics();
$topRoutes = $metrics->getTopRoutes(10);
$slowest = $metrics->getSlowestRoutes(10);
```

### CachePlugin
Enhanced caching with automatic warming:
```php
use PivotPHP\Routing\Plugins\CachePlugin;
use PivotPHP\Routing\Cache\FileCacheStrategy;

$cache = new CachePlugin(
    new FileCacheStrategy('/cache'),
    ['auto_warm' => true]
);
$router->registerPlugin($cache);
```

### DebugPlugin
Development debugging tools:
```php
use PivotPHP\Routing\Plugins\DebugPlugin;

$debug = new DebugPlugin();
$router->registerPlugin($debug);

// Inspect routes
$allRoutes = $debug->dumpRoutes();
$tree = $debug->getRouteTree();
$stats = $debug->getRouteStats();
```

## Version Information

- **Current Version**: 1.0.0 (Initial Release)
- **PHP Requirements**: 8.1+
- **Dependencies**: PSR-7, PSR-15, PSR-6/PSR-16
- **License**: MIT

## Migration from pivotphp-core v1.x

### Namespace Updates
```php
// Old
use PivotPHP\Core\Routing\Router;
use PivotPHP\Core\Routing\Route;

// New
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\Route;
```

### Cache System
```php
// Old (implicit in-memory caching)
Router::get('/users', $handler);

// New (explicit strategy)
use PivotPHP\Routing\Cache\FileCacheStrategy;

$cache = new FileCacheStrategy('/cache');
$router->setCacheStrategy($cache);
$router::get('/users', $handler);
```

### Plugin System (New Feature)
```php
// Enable metrics tracking
$router->registerPlugin(new MetricsPlugin());

// Enable debug tools
$router->registerPlugin(new DebugPlugin());
```

## Important Notes

- This package is designed to work independently or with PivotPHP Core v2.0+
- All performance optimizations from pivotphp-core v1.x are preserved
- Plugin system allows extensibility without modifying core code
- File caching improves startup performance in production
- Memory caching ideal for development environments

## Support and Community

- **GitHub**: https://github.com/PivotPHP/pivotphp-core-routing
- **Packagist**: https://packagist.org/packages/pivotphp/core-routing
