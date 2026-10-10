<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouterTest extends TestCase
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



    public function testBasicGetRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('GET route works');
        };

        $this->router->get('/test', $handler);
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicPostRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('POST route works');
        };

        $this->router->post('/test', $handler);
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('POST', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicPutRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('PUT route works');
        };

        $this->router->put('/test', $handler);
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('PUT', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicDeleteRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('DELETE route works');
        };

        $this->router->delete('/test', $handler);
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('DELETE', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testMultipleRoutes(): void
    {
        $this->router->get(
            '/users',
            function () {
            }
        );
        $this->router->post(
            '/users',
            function () {
            }
        );
        $this->router->get(
            '/posts',
            function () {
            }
        );

        $routes = $this->router->getRoutes();
        $this->assertCount(3, $routes);
    }

    public function testRouteWithParameters(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('User route works');
        };

        $this->router->get('/users/:id', $handler);
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:id', $routes[0]['path']);
    }

    public function testRouteWithMultipleParameters(): void
    {
        $this->router->get(
            '/users/:userId/posts/:postId',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:userId/posts/:postId', $routes[0]['path']);
    }

    public function testRouteWithRegexConstraints(): void
    {
        $this->router->get(
            '/users/:id<\\d+>',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:id<\\d+>', $routes[0]['path']);
    }

    public function testArrayCallableHandler(): void
    {
        $handler = [TestController::class, 'staticIndex'];
        $this->router->get('/test', $handler);

        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals($handler, $routes[0]['handler']);
    }


    public function testClearRoutes(): void
    {
        $this->router->get(
            '/test1',
            function () {
            }
        );
        $this->router->get(
            '/test2',
            function () {
            }
        );
        $this->assertCount(2, $this->router->getRoutes());

        $this->router->clear();
        $this->assertCount(0, $this->router->getRoutes());
    }

    public function testOptionsMethod(): void
    {
        $this->router->options(
            '/test',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('OPTIONS', $routes[0]['method']);
    }

    public function testPatchMethod(): void
    {
        $this->router->patch(
            '/test',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('PATCH', $routes[0]['method']);
    }

    public function testHeadMethod(): void
    {
        $this->router->head(
            '/test',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('HEAD', $routes[0]['method']);
    }

    public function testAnyMethod(): void
    {
        $this->router->clear(); // Clear before this test to ensure clean state
        $this->router->any(
            '/test',
            function () {
            }
        );
        $routes = $this->router->getRoutes();

        // ANY method may register multiple routes for all HTTP methods
        $this->assertGreaterThan(0, count($routes));

        // Check that at least one route exists
        $this->assertNotEmpty($routes);
    }

    public function testRouteGroup(): void
    {
        $this->router->group(
            '/api',
            function ($router) {
                $router->get(
                    '/users',
                    function () {
                    }
                );
                $router->post(
                    '/users',
                    function () {
                    }
                );
            }
        );

        $routes = $this->router->getRoutes();
        $this->assertCount(2, $routes);

        // Check that routes have the group prefix
        $this->assertEquals('/api/users', $routes[0]['path']);
        $this->assertEquals('/api/users', $routes[1]['path']);
    }
}

// Test helper class
class TestController
{
    public function index($req, $res)
    {
        return $res->withBody('Controller method works');
    }

    public static function staticIndex($req, $res)
    {
        return $res->withBody('Static controller method works');
    }
}
