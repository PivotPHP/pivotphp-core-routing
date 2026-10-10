<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Prova o isolamento de estado entre instâncias de Router (SPEC-076).
 *
 * Múltiplas instâncias de Router no mesmo processo PHP devem manter tabelas de rotas,
 * prefixos e middlewares 100% isolados, sem vazamento mútuo.
 */
final class RouterInstanceStateIsolationTest extends TestCase
{
    public function testTwoRouterInstancesHaveCompletelyIsolatedRoutes(): void
    {
        $routerA = new Router();
        $routerB = new Router();

        $handlerA = static fn () => 'admin';
        $handlerB = static fn () => 'public';

        $routerA->get('/admin/users', $handlerA);
        $routerB->get('/public/home', $handlerB);

        // Router A contém apenas /admin/users
        $this->assertCount(1, $routerA->getRoutes());
        $this->assertSame('/admin/users', $routerA->getRoutes()[0]['path']);
        $this->assertNotNull($routerA->identify('GET', '/admin/users'));
        $this->assertNull($routerA->identify('GET', '/public/home'));

        // Router B contém apenas /public/home
        $this->assertCount(1, $routerB->getRoutes());
        $this->assertSame('/public/home', $routerB->getRoutes()[0]['path']);
        $this->assertNotNull($routerB->identify('GET', '/public/home'));
        $this->assertNull($routerB->identify('GET', '/admin/users'));
    }

    public function testClearOnOneInstanceDoesNotAffectAnotherInstance(): void
    {
        $routerA = new Router();
        $routerB = new Router();

        $routerA->get('/alpha', static fn () => 'alpha');
        $routerB->get('/beta', static fn () => 'beta');

        $routerA->clear();

        $this->assertCount(0, $routerA->getRoutes());
        $this->assertCount(1, $routerB->getRoutes());
        $this->assertNotNull($routerB->identify('GET', '/beta'));
    }

    public function testGroupPrefixAndMiddlewaresDoNotLeakBetweenInstances(): void
    {
        $routerA = new Router();
        $routerB = new Router();

        $mwA = static fn ($req, $res, $next) => $next($req, $res);

        $routerA->group('/admin', function ($group) {
            $group->get('/dashboard', static fn () => 'dash');
        }, [$mwA]);

        $routerB->get('/dashboard', static fn () => 'public dash');

        $routeA = $routerA->identify('GET', '/admin/dashboard');
        $this->assertNotNull($routeA);
        $this->assertCount(1, $routeA['middlewares']);

        $routeB = $routerB->identify('GET', '/dashboard');
        $this->assertNotNull($routeB);
        $this->assertCount(0, $routeB['middlewares']);
        $this->assertNull($routerB->identify('GET', '/admin/dashboard'));
    }
}

