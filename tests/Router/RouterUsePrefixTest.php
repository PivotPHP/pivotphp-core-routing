<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Router;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Router::use($prefix, ...$middlewares) attaches middlewares to a path prefix and must not change
 * the path of routes registered afterwards (SPEC-053).
 */
final class RouterUsePrefixTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear();
    }

    protected function tearDown(): void
    {
        Router::clear();
    }

    /**
     * @return array<string, array<int, callable>>
     */
    private static function middlewaresByPath(): array
    {
        $map = [];
        foreach (Router::getRoutes() as $route) {
            $map[$route['path']] = $route['middlewares'];
        }

        return $map;
    }

    public function testUseDoesNotPrefixLaterRoutes(): void
    {
        $mw = static fn ($req, $res, $next) => $next();

        Router::use('/api', $mw);
        Router::get('/health', static fn () => 'ok');
        Router::get('/users', static fn () => 'ok');

        $this->assertSame(['/health', '/users'], array_column(Router::getRoutes(), 'path'));
        $this->assertNotNull(Router::identify('GET', '/health'));
        $this->assertNull(Router::identify('GET', '/api/health'));
    }

    public function testUseMiddlewareAppliesOnlyToRoutesUnderThePrefix(): void
    {
        $mw = static fn ($req, $res, $next) => $next();

        Router::use('/api', $mw);
        Router::get('/api/items', static fn () => 'ok');
        Router::get('/health', static fn () => 'ok');

        $middlewares = self::middlewaresByPath();
        $this->assertSame([$mw], $middlewares['/api/items']);
        $this->assertSame([], $middlewares['/health']);
    }

    public function testUseWithoutMiddlewareKeepsPathsUntouched(): void
    {
        Router::use('/api/prot');
        Router::get('/direct', static fn () => 'ok');

        $this->assertSame(['/direct'], array_column(Router::getRoutes(), 'path'));
    }

    public function testGroupStillPrefixesItsOwnRoutes(): void
    {
        Router::use('/api', static fn ($req, $res, $next) => $next());
        Router::group('/v1', function (): void {
            Router::get('/status', static fn () => 'ok');
        });
        Router::get('/after', static fn () => 'ok');

        $this->assertSame(['/v1/status', '/after'], array_column(Router::getRoutes(), 'path'));
    }
}
