# PivotPHP Core Routing

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PSR-7](https://img.shields.io/badge/PSR--7-compliant-brightgreen)](https://www.php-fig.org/psr/psr-7/)
[![PSR-15](https://img.shields.io/badge/PSR--15-compliant-brightgreen)](https://www.php-fig.org/psr/psr-15/)

Modular, high-performance routing system for PivotPHP with Express.js-inspired API and full PSR compliance.

## Features

- **Express.js-Inspired API**: Familiar routing patterns (`get()`, `post()`, `put()`, `delete()`, etc.)
- **High Performance**: Multi-level caching, route indexing, and memory optimization
- **PSR Compliant**: Full PSR-7 (HTTP), PSR-15 (Middleware), PSR-6/PSR-16 (Cache) support
- **Plugin System**: Extensible architecture with built-in plugins
- **File Caching**: Persistent route compilation for faster startup
- **Static File Serving**: Express-style static file management
- **Type Safety**: Strict typing with PHPStan Level 9 compliance
- **Modular**: Use independently or integrate with PivotPHP Core

## Installation

```bash
composer require pivotphp/core-routing
```

## Quick Start

```php
use PivotPHP\Routing\Router;

// Create router instance
$router = new Router();

// Define routes
$router->get('/users', function($req, $res) {
    return $res->json(['users' => []]);
});

$router->post('/users', [UserController::class, 'store']);

// Route with parameters
$router->get('/users/:id', function($req, $res) {
    $userId = $req->param('id');
    return $res->json(['user' => ['id' => $userId]]);
});

// Route groups with prefix and middleware
$router->group('/api', function() use ($router) {
    $router->get('/status', function($req, $res) {
        return $res->json(['status' => 'ok']);
    });
}, $authMiddleware);

// Match route
$route = $router::identify($method, $path);
```

## Advanced Features

### Plugin System

```php
use PivotPHP\Routing\Plugins\MetricsPlugin;

$router->registerPlugin(new MetricsPlugin());
```

### File Caching

```php
use PivotPHP\Routing\Cache\FileCacheStrategy;

$cache = new FileCacheStrategy('/path/to/cache');
$router->setCacheStrategy($cache);

// Warm cache
$router->warmCache();
```

### Static Files

```php
use PivotPHP\Routing\Static\StaticFileManager;

$router->static('/public', __DIR__ . '/public');
```

## Requirements

- PHP 8.1 or higher
- PSR-7 HTTP Message implementation
- PSR-15 HTTP Server Handler implementation

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Static analysis
composer phpstan

# Code style check
composer cs:check

# Fix code style
composer cs:fix

# All quality checks
composer quality:check
```

## Performance

PivotPHP Core Routing is designed for high performance:

- **Multi-level caching**: Exact match, compiled routes, pattern cache
- **Route indexing**: O(1) group lookups, method-based indexing
- **Memory management**: Automatic garbage collection, usage tracking
- **Static/Dynamic separation**: Optimized matching strategies

## Documentation

- [Full Documentation](https://pivotphp.com/docs/routing)
- [Migration Guide](UPGRADE.md)
- [Plugin Development](docs/plugins.md)
- [Performance Tuning](docs/performance.md)

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Community

- [GitHub Issues](https://github.com/PivotPHP/pivotphp-core-routing/issues)
- [Contributing](CONTRIBUTING.md)

## Credits

Created by Carlos Fernandes and the PivotPHP community.

Inspired by Express.js routing and built with modern PHP best practices.
