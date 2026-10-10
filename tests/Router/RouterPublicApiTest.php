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
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        $this->router->clear();
    }



    public function testStandardHttpMethodsAreAcceptedByDefault(): void
    {
        $methods = $this->router->getHttpMethodsAccepted();

        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'] as $method) {
            $this->assertContains($method, $methods);
        }
    }

    public function testAddHttpMethodNormalisesCaseWithoutDuplicates(): void
    {
        $this->router->addHttpMethod('purge');
        $this->router->addHttpMethod('PURGE');

        $methods = $this->router->getHttpMethodsAccepted();

        $this->assertContains('PURGE', $methods);
        $this->assertNotContains('purge', $methods);
        $this->assertCount(1, array_keys($methods, 'PURGE', true));
    }

    public function testRegisteredRouteKeepsMethodPathAndHandler(): void
    {
        $handler = static fn () => 'test';

        $this->router->get('/test', $handler);

        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/test', $routes[0]['path']);
        $this->assertSame($handler, $routes[0]['handler']);
    }

    public function testIdentifyReturnsRouteOrNull(): void
    {
        $this->router->get('/test', static fn () => 'test');

        $found = $this->router->identify('GET', '/test');
        $this->assertIsArray($found);
        $this->assertSame('/test', $found['path']);

        $this->assertNull($this->router->identify('GET', '/missing'));
        $this->assertNull($this->router->identify('POST', '/test'));
    }

    public function testToStringListsRegisteredRoutes(): void
    {
        $this->assertSame('', $this->router->toString());

        $this->router->get('/a', static fn () => 'a');
        $this->router->post('/b', static fn () => 'b');

        $this->assertSame("GET /a => Callable\nPOST /b => Callable\n", $this->router->toString());
    }

    public function testClearRemovesRoutes(): void
    {
        $this->router->get('/a', static fn () => 'a');

        $this->router->clear();

        $this->assertSame([], $this->router->getRoutes());
    }
}
