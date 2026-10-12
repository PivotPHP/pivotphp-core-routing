<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Testes de caracterização da API de roteamento — SPEC-114 Fase 1.
 *
 * Garante que o comportamento atual da sintaxe de rotas e resolução
 * esteja 100% blindado contra regressões.
 */
class RouterCharacterizationTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    public function testStaticRoutesAllVerbs(): void
    {
        $this->router->get('/health', static fn () => 'get');
        $this->router->post('/items', static fn () => 'post');
        $this->router->put('/items', static fn () => 'put');
        $this->router->delete('/items', static fn () => 'delete');
        $this->router->patch('/items', static fn () => 'patch');

        $get = $this->router->identify('GET', '/health');
        $this->assertNotNull($get);
        $this->assertSame('GET', $get['method']);
        $this->assertSame('/health', $get['path']);

        $post = $this->router->identify('POST', '/items');
        $this->assertNotNull($post);
        $this->assertSame('POST', $post['method']);

        $put = $this->router->identify('PUT', '/items');
        $this->assertNotNull($put);
        $this->assertSame('PUT', $put['method']);

        $delete = $this->router->identify('DELETE', '/items');
        $this->assertNotNull($delete);
        $this->assertSame('DELETE', $delete['method']);

        $patch = $this->router->identify('PATCH', '/items');
        $this->assertNotNull($patch);
        $this->assertSame('PATCH', $patch['method']);
    }

    public function testExplicitOptionsRoute(): void
    {
        $this->router->options('/api/custom', static fn () => 'custom options');

        $route = $this->router->identify('OPTIONS', '/api/custom');
        $this->assertNotNull($route);
        $this->assertSame('OPTIONS', $route['method']);
    }

    public function testTrailingSlashNormalization(): void
    {
        $this->router->get('/api/users', static fn () => 'users');

        $match1 = $this->router->identify('GET', '/api/users');
        $match2 = $this->router->identify('GET', '/api/users/');

        $this->assertNotNull($match1);
        $this->assertNotNull($match2);
        $this->assertSame($match1['path'], $match2['path']);
    }

    public function testDynamicRouteParams(): void
    {
        $this->router->get('/users/:id', static fn () => 'show');

        $match = $this->router->identify('GET', '/users/42');
        $this->assertNotNull($match);
        $this->assertSame(['id' => '42'], $match['matched_params']);
    }

    public function testDynamicRouteRegexConstraint(): void
    {
        $this->router->get('/users/:id<\d+>', static fn () => 'digits');

        $matchValid = $this->router->identify('GET', '/users/123');
        $this->assertNotNull($matchValid);
        $this->assertSame(['id' => '123'], $matchValid['matched_params']);

        $matchInvalid = $this->router->identify('GET', '/users/abc');
        $this->assertNull($matchInvalid);
    }

    public function testBraceParameterSyntax(): void
    {
        $this->router->get('/items/{slug}', static fn () => 'slug');

        $match = $this->router->identify('GET', '/items/my-item');
        $this->assertNotNull($match);
        $this->assertSame(['slug' => 'my-item'], $match['matched_params']);
    }

    public function testMultipleDynamicParameters(): void
    {
        $this->router->get('/orgs/:orgId/repos/:repoId', static fn () => 'repo');

        $match = $this->router->identify('GET', '/orgs/pivot/repos/core');
        $this->assertNotNull($match);
        $this->assertSame(['orgId' => 'pivot', 'repoId' => 'core'], $match['matched_params']);
    }

    public function testIdentifyReturnsNullForMethodMismatch(): void
    {
        $this->router->post('/api/orders', static fn () => 'create');

        $this->assertNull($this->router->identify('GET', '/api/orders'));
        $this->assertNull($this->router->identify('DELETE', '/api/orders'));

        // allowedMethods must find POST and not be empty
        $allowed = $this->router->allowedMethods('/api/orders');
        $this->assertContains('POST', $allowed);
    }

    public function testIdentifyReturnsNullForNonExistentRoute(): void
    {
        $this->router->get('/existing', static fn () => 'ok');

        $this->assertNull($this->router->identify('GET', '/not-found'));
        $this->assertSame([], $this->router->allowedMethods('/not-found'));
    }

    public function testNestedGroupsWithInheritedMiddlewares(): void
    {
        $mwGlobal = static fn () => 'global';
        $mwAdmin = static fn () => 'admin';
        $mwUsers = static fn () => 'users';

        $this->router->group('/admin', function ($adminRouter) use ($mwUsers) {
            $adminRouter->group('/users', function ($usersRouter) {
                $usersRouter->get('/list', static fn () => 'list');
            }, [$mwUsers]);
        }, [$mwAdmin]);

        $match = $this->router->identify('GET', '/admin/users/list');
        $this->assertNotNull($match);
        $this->assertSame('/admin/users/list', $match['path']);
        $this->assertCount(2, $match['middlewares']);
        $this->assertSame([$mwAdmin, $mwUsers], $match['middlewares']);
    }
}
