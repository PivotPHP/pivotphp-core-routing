<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear(); // Clear any existing routes
    }

    protected function tearDown(): void
    {
        Router::clear();
    }

    public function testBasicGetRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('GET route works');
        };

        Router::get('/test', $handler);
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicPostRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('POST route works');
        };

        Router::post('/test', $handler);
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('POST', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicPutRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('PUT route works');
        };

        Router::put('/test', $handler);
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('PUT', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testBasicDeleteRoute(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('DELETE route works');
        };

        Router::delete('/test', $handler);
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('DELETE', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testMultipleRoutes(): void
    {
        Router::get(
            '/users',
            function () {
            }
        );
        Router::post(
            '/users',
            function () {
            }
        );
        Router::get(
            '/posts',
            function () {
            }
        );

        $routes = Router::getRoutes();
        $this->assertCount(3, $routes);
    }

    public function testRouteWithParameters(): void
    {
        $handler = function ($req, $res) {
            return $res->withBody('User route works');
        };

        Router::get('/users/:id', $handler);
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:id', $routes[0]['path']);
    }

    public function testRouteWithMultipleParameters(): void
    {
        Router::get(
            '/users/:userId/posts/:postId',
            function () {
            }
        );
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:userId/posts/:postId', $routes[0]['path']);
    }

    public function testRouteWithRegexConstraints(): void
    {
        Router::get(
            '/users/:id<\\d+>',
            function () {
            }
        );
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('/users/:id<\\d+>', $routes[0]['path']);
    }

    public function testArrayCallableHandler(): void
    {
        $handler = [TestController::class, 'staticIndex'];
        Router::get('/test', $handler);

        $routes = Router::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals($handler, $routes[0]['handler']);
    }


    public function testClearRoutes(): void
    {
        Router::get(
            '/test1',
            function () {
            }
        );
        Router::get(
            '/test2',
            function () {
            }
        );
        $this->assertCount(2, Router::getRoutes());

        Router::clear();
        $this->assertCount(0, Router::getRoutes());
    }

    public function testGetStats(): void
    {
        $stats = Router::getStats();

        $this->assertIsArray($stats);
        // Just verify that stats can be retrieved without checking specific content
        // as the internal structure may be complex
    }

    public function testOptionsMethod(): void
    {
        Router::options(
            '/test',
            function () {
            }
        );
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('OPTIONS', $routes[0]['method']);
    }

    public function testPatchMethod(): void
    {
        Router::patch(
            '/test',
            function () {
            }
        );
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('PATCH', $routes[0]['method']);
    }

    public function testHeadMethod(): void
    {
        Router::head(
            '/test',
            function () {
            }
        );
        $routes = Router::getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('HEAD', $routes[0]['method']);
    }

    public function testAnyMethod(): void
    {
        Router::clear(); // Clear before this test to ensure clean state
        Router::any(
            '/test',
            function () {
            }
        );
        $routes = Router::getRoutes();

        // ANY method may register multiple routes for all HTTP methods
        $this->assertGreaterThan(0, count($routes));

        // Check that at least one route exists
        $this->assertNotEmpty($routes);
    }

    public function testRouteGroup(): void
    {
        Router::group(
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

        $routes = Router::getRoutes();
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
