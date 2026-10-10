<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Router public API (HTTP methods, registration, introspection).
 *
 * Migrated from pivotphp-core (tests/Controller/RouterTest.php and RouterBasicTest.php), which
 * tested this package's Router; assertions made explicit (the originals swallowed exceptions).
 */
final class RouterPublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear();
    }

    protected function tearDown(): void
    {
        Router::clear();
    }

    public function testStandardHttpMethodsAreAcceptedByDefault(): void
    {
        $methods = Router::getHttpMethodsAccepted();

        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'] as $method) {
            $this->assertContains($method, $methods);
        }
    }

    public function testAddHttpMethodNormalisesCaseWithoutDuplicates(): void
    {
        Router::addHttpMethod('purge');
        Router::addHttpMethod('PURGE');

        $methods = Router::getHttpMethodsAccepted();

        $this->assertContains('PURGE', $methods);
        $this->assertNotContains('purge', $methods);
        $this->assertCount(1, array_keys($methods, 'PURGE', true));
    }

    public function testRegisteredRouteKeepsMethodPathAndHandler(): void
    {
        $handler = static fn () => 'test';

        Router::get('/test', $handler);

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/test', $routes[0]['path']);
        $this->assertSame($handler, $routes[0]['handler']);
    }

    public function testIdentifyReturnsRouteOrNull(): void
    {
        Router::get('/test', static fn () => 'test');

        $found = Router::identify('GET', '/test');
        $this->assertIsArray($found);
        $this->assertSame('/test', $found['path']);

        $this->assertNull(Router::identify('GET', '/missing'));
        $this->assertNull(Router::identify('POST', '/test'));
    }

    public function testToStringListsRegisteredRoutes(): void
    {
        $this->assertSame('', Router::toString());

        Router::get('/a', static fn () => 'a');
        Router::post('/b', static fn () => 'b');

        $this->assertSame("GET /a => Callable\nPOST /b => Callable\n", Router::toString());
    }

    public function testClearRemovesRoutes(): void
    {
        Router::get('/a', static fn () => 'a');

        Router::clear();

        $this->assertSame([], Router::getRoutes());
    }
}
