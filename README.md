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
  `head()`, `any()`, `match()` and `add()`.
- **Instance-based state**: each `Router` instance owns its routes, groups and middlewares — several
  routers (e.g. several applications) in the same PHP process never share routes.
- **Groups & prefixes**: `group()` prefixes routes (nested groups supported); `use()` attaches middlewares to a path prefix without changing route paths.
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

$router = new Router();

// Define routes
$router->get('/users', function ($req, $res) {
    return $res->json(['users' => []]);
});

$router->post('/users', [UserController::class, 'store']);

// Route with parameters — both ':id' and '{id}' syntaxes are supported
$router->get('/users/:id', function ($req, $res) {
    $userId = $req->param('id');
    return $res->json(['user' => ['id' => $userId]]);
});

$router->get('/books/{isbn}', function ($req, $res) {
    $isbn = $req->param('isbn');
    return $res->json(['book' => ['isbn' => $isbn]]);
});

// Route groups with prefix and middleware
$router->group('/api', function ($router) {
    $router->get('/status', function ($req, $res) {
        return $res->json(['status' => 'ok']);
    });
}, [$authMiddleware]);

// Match route
$route = $router->identify('GET', '/users/42');
```

### Handlers

Supported handler syntaxes: `Closure`, named functions, or array callables
`[Class::class, 'method']`. The legacy `'Controller@method'` string format is **not** supported.

### Static files

```php
use PivotPHP\Routing\Router\StaticFileManager;

StaticFileManager::registerDirectory('/public', __DIR__ . '/public', [], $router);
```

The last argument is the router that receives the routes; registering the same directory in another
router publishes the files there too.

Each file becomes a `GET` route when the directory is registered. Only files with an allowed extension
are published; **hidden files and anything inside hidden directories** (`.env*`, `.git/`, `.well-known/`)
are never published, and **symlinks are followed only when their target stays inside the directory**.
Register routes explicitly for files you need from a hidden path (e.g. `/.well-known/...`).

## API reference

### `Router` (instance)

| Method | Description |
|---|---|
| `add($method, $path, $handler, $metadata = [], ...$middlewares)` | Register a route. |
| `get/post/put/delete/patch/options/head/any($path, $handler, ...)` | HTTP verb shortcuts. |
| `match($methods, $path, $handler, ...)` | Register the same handler for several methods. |
| `group($prefix, $callback, $middlewares = [])` | Register a route group. |
| `use($prefix, ...$middlewares)` | Attach middlewares to routes registered afterwards whose path starts with `$prefix`; does not change route paths. |
| `addHttpMethod($method)` | Register an additional HTTP method. |
| `identify($method, $path)` | Match a route; returns the route array or `null`. |
| `allowedMethods($path)` | Methods that have a route for `$path`. |
| `Router::compilePattern($path)` (static) | Compile a path to regex + parameter list. |
| `Router::isStaticRoute($path)` (static) | Whether a path has no parameters. |
| `getRoutes()` | All registered routes. |
| `getHttpMethodsAccepted()` | Accepted HTTP methods. |
| `clear()` | Clear this router's routes/state. |

### Migrating from 2.x

In 2.x every method was static and all code shared one global route table. In 3.0 create a `Router`
and call the methods on it. For code that cannot be migrated at once, the deprecated
`RouterFacade` forwards static calls to one shared instance (`RouterFacade::get(...)`,
`Router::default()`), which keeps the old global behaviour.

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
