<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Router;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * $this->router->use($prefix, ...$middlewares) attaches middlewares to a path prefix and must not change
 * the path of routes registered afterwards (SPEC-053).
 */
final class RouterUsePrefixTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        $this->router->clear();
    }



    /**
     * @return array<string, array<int, callable>>
     */
    private function middlewaresByPath(): array
    {
        $map = [];
        foreach ($this->router->getRoutes() as $route) {
            $map[$route['path']] = $route['middlewares'];
        }

        return $map;
    }

    public function testUseDoesNotPrefixLaterRoutes(): void
    {
        $mw = static fn ($req, $res, $next) => $next();

        $this->router->use('/api', $mw);
        $this->router->get('/health', static fn () => 'ok');
        $this->router->get('/users', static fn () => 'ok');

        $this->assertSame(['/health', '/users'], array_column($this->router->getRoutes(), 'path'));
        $this->assertNotNull($this->router->identify('GET', '/health'));
        $this->assertNull($this->router->identify('GET', '/api/health'));
    }

    public function testUseMiddlewareAppliesOnlyToRoutesUnderThePrefix(): void
    {
        $mw = static fn ($req, $res, $next) => $next();

        $this->router->use('/api', $mw);
        $this->router->get('/api/items', static fn () => 'ok');
        $this->router->get('/health', static fn () => 'ok');

        $middlewares = $this->middlewaresByPath();
        $this->assertSame([$mw], $middlewares['/api/items']);
        $this->assertSame([], $middlewares['/health']);
    }

    public function testUseWithoutMiddlewareKeepsPathsUntouched(): void
    {
        $this->router->use('/api/prot');
        $this->router->get('/direct', static fn () => 'ok');

        $this->assertSame(['/direct'], array_column($this->router->getRoutes(), 'path'));
    }

    public function testGroupStillPrefixesItsOwnRoutes(): void
    {
        $this->router->use('/api', static fn ($req, $res, $next) => $next());
        $this->router->group('/v1', function (): void {
            $this->router->get('/status', static fn () => 'ok');
        });
        $this->router->get('/after', static fn () => 'ok');

        $this->assertSame(['/v1/status', '/after'], array_column($this->router->getRoutes(), 'path'));
    }
}
