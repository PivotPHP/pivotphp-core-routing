# PivotPHP Core Routing

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PSR-7](https://img.shields.io/badge/PSR--7-compliant-brightgreen)](https://www.php-fig.org/psr/psr-7/)
[![PSR-15](https://img.shields.io/badge/PSR--15-compliant-brightgreen)](https://www.php-fig.org/psr/psr-15/)

Simple, focused routing engine for PivotPHP with an Express.js-inspired API. It does one thing
well: **register routes, compile patterns and match paths** — no caching, no plugins, no
premature optimization.

## Features

- **Express.js-inspired API**: `get()`, `post()`, `put()`, `delete()`, `patch()`, `options()`,
  `head()`, `any()` and `add()`.
- **Groups & prefixes**: `group()` and `use()` (nested groups supported).
- **Pattern compilation**: `:param`, `{param}` and `<constraint>` with shortcuts (`int`, `slug`,
  `alpha`, `alnum`, `uuid`, `date`, `year`, `month`, `day`).
- **Static file serving**: `StaticFileManager` registers files as routes.
- **Type safety**: strict typing, PHPStan level 9.

## Installation

```bash
composer require pivotphp/core-routing
```

## Quick Start

```php
use PivotPHP\Routing\Router\Router;

// Define routes
Router::get('/users', function ($req, $res) {
    return $res->json(['users' => []]);
});

Router::post('/users', [UserController::class, 'store']);

// Route with parameters — both ':id' and '{id}' syntaxes are supported
Router::get('/users/:id', function ($req, $res) {
    $userId = $req->param('id');
    return $res->json(['user' => ['id' => $userId]]);
});

Router::get('/books/{isbn}', function ($req, $res) {
    $isbn = $req->param('isbn');
    return $res->json(['book' => ['isbn' => $isbn]]);
});

// Route groups with prefix and middleware
Router::group('/api', function ($router) {
    $router->get('/status', function ($req, $res) {
        return $res->json(['status' => 'ok']);
    });
}, [$authMiddleware]);

// Match route
$route = Router::identify('GET', '/users/42');
```

### Handlers

Supported handler syntaxes: `Closure`, named functions, or array callables
`[Class::class, 'method']`. The legacy `'Controller@method'` string format is **not** supported.

### Static files

```php
use PivotPHP\Routing\Router\StaticFileManager;

StaticFileManager::registerDirectory('/public', __DIR__ . '/public');
```

## API reference

### `Router` (static facade)

| Method | Description |
|---|---|
| `add($method, $path, $handler, $metadata = [], ...$middlewares)` | Register a route. |
| `get/post/put/delete/patch/options/head/any($path, $handler, ...)` | HTTP verb shortcuts. |
| `group($prefix, $callback, $middlewares = [])` | Register a route group. |
| `use($prefix, ...$middlewares)` | Register group middlewares by prefix. |
| `addHttpMethod($method)` | Register an additional HTTP method. |
| `identify($method, $path)` | Match a route; returns the route array or `null`. |
| `compilePattern($path)` | Compile a path to regex + parameter list. |
| `isStaticRoute($path)` | Whether a path has no parameters. |
| `getRoutes()` | All registered routes. |
| `getHttpMethodsAccepted()` | Accepted HTTP methods. |
| `clear()` | Clear all registered routes/state. |

## Requirements

- PHP 8.1 or higher
- PSR-7 HTTP Message implementation
- PSR-15 HTTP Server Handler implementation

## Testing

```bash
composer test           # PHPUnit
composer phpstan        # Static analysis (level 9)
composer cs:check       # PSR-12 style check
composer quality:check  # phpstan + cs:check + test
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Community

- [GitHub Issues](https://github.com/PivotPHP/pivotphp-core-routing/issues)
- [Contributing](CONTRIBUTING.md)

## Credits

Created by Caio Alberto Fernandes and the PivotPHP community.

Inspired by Express.js routing and built with modern PHP best practices.
