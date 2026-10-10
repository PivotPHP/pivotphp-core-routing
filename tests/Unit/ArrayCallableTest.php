<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Tests\Unit\TestController;

/**
 * Tests for array callable routing functionality
 */
class ArrayCallableTest extends TestCase
{
    private Router $router;

    private TestController $controller;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->clear();
        $this->controller = new TestController();
    }

    protected function tearDown(): void
    {
        $this->router->clear();
    }

    /**
     * @test
     */
    public function testArrayCallableWithInstanceMethod(): void
    {
        $this->router->get('/test', [$this->controller, 'index']);

        $route = $this->router->identify('GET', '/test');

        $this->assertNotNull($route);
        $this->assertEquals('/test', $route['path']);
        $this->assertIsCallable($route['handler']);

        // Verify the handler is the correct array callable
        $this->assertIsArray($route['handler']);
        $this->assertCount(2, $route['handler']);
        $this->assertSame($this->controller, $route['handler'][0]);
        $this->assertEquals('index', $route['handler'][1]);
    }

    /**
     * @test
     */
    public function testArrayCallableWithStaticMethod(): void
    {
        $this->router->get('/static', [TestController::class, 'staticMethod']);

        $route = $this->router->identify('GET', '/static');

        $this->assertNotNull($route);
        $this->assertEquals('/static', $route['path']);
        $this->assertIsCallable($route['handler']);

        // Verify the handler is the correct array callable
        $this->assertIsArray($route['handler']);
        $this->assertCount(2, $route['handler']);
        $this->assertEquals(TestController::class, $route['handler'][0]);
        $this->assertEquals('staticMethod', $route['handler'][1]);
    }

    /**
     * @test
     */
    public function testArrayCallableWithParameters(): void
    {
        $this->router->get('/users/:userId/posts/:postId', [$this->controller, 'withParameters']);

        $route = $this->router->identify('GET', '/users/123/posts/456');

        $this->assertNotNull($route);
        $this->assertEquals('/users/:userId/posts/:postId', $route['path']);
        $this->assertIsCallable($route['handler']);

        // Verify parameters are extracted
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('123', $route['matched_params']['userId']);
        $this->assertEquals('456', $route['matched_params']['postId']);
    }

    /**
     * @test
     */
    public function testHealthCheckRoute(): void
    {
        // This is the specific case mentioned by the user
        $this->router->get('/health', [$this->controller, 'healthCheck']);

        $route = $this->router->identify('GET', '/health');

        $this->assertNotNull($route);
        $this->assertEquals('/health', $route['path']);
        $this->assertIsCallable($route['handler']);

        // Verify it's the correct method
        $this->assertIsArray($route['handler']);
        $this->assertEquals('healthCheck', $route['handler'][1]);
    }

    /**
     * @test
     */
    public function testMultipleArrayCallables(): void
    {
        $this->router->get('/method1', [$this->controller, 'index']);
        $this->router->get('/method2', [$this->controller, 'healthCheck']);
        $this->router->get('/static', [TestController::class, 'staticMethod']);

        $route1 = $this->router->identify('GET', '/method1');
        $route2 = $this->router->identify('GET', '/method2');
        $route3 = $this->router->identify('GET', '/static');

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
        $this->assertNotNull($route3);

        $this->assertEquals('index', $route1['handler'][1]);
        $this->assertEquals('healthCheck', $route2['handler'][1]);
        $this->assertEquals('staticMethod', $route3['handler'][1]);
    }

    /**
     * @test
     */
    public function testArrayCallableInGroup(): void
    {
        $this->router->group(
            '/api/v1',
            function ($router) {
                $router->get('/health', [$this->controller, 'healthCheck']);
                $router->get('/users/:id', [$this->controller, 'show']);
            }
        );

        $healthRoute = $this->router->identify('GET', '/api/v1/health');
        $userRoute = $this->router->identify('GET', '/api/v1/users/123');

        $this->assertNotNull($healthRoute);
        $this->assertNotNull($userRoute);

        $this->assertEquals('/api/v1/health', $healthRoute['path']);
        $this->assertEquals('/api/v1/users/:id', $userRoute['path']);

        $this->assertIsCallable($healthRoute['handler']);
        $this->assertIsCallable($userRoute['handler']);
    }

    /**
     * @test
     */
    public function testMixedCallableTypes(): void
    {
        // Mix different callable types
        $this->router->get(
            '/closure',
            function ($req, $res) {
                return 'closure';
            }
        );

        $this->router->get('/array', [$this->controller, 'index']);

        $this->router->get('/static', [TestController::class, 'staticMethod']);

        $closureRoute = $this->router->identify('GET', '/closure');
        $arrayRoute = $this->router->identify('GET', '/array');
        $staticRoute = $this->router->identify('GET', '/static');

        $this->assertNotNull($closureRoute);
        $this->assertNotNull($arrayRoute);
        $this->assertNotNull($staticRoute);

        // All should be callable
        $this->assertIsCallable($closureRoute['handler']);
        $this->assertIsCallable($arrayRoute['handler']);
        $this->assertIsCallable($staticRoute['handler']);

        // But different types
        $this->assertInstanceOf(\Closure::class, $closureRoute['handler']);
        $this->assertIsArray($arrayRoute['handler']);
        $this->assertIsArray($staticRoute['handler']);
    }

    /**
     * @test
     */
    public function testInvalidArrayCallable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route handler validation failed: Method');

        // Try to register an invalid callable
        $this->router->get('/invalid', [$this->controller, 'nonExistentMethod']);
    }

    /**
     * @test
     */
    public function testInvalidStaticCallable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route handler validation failed: Static method');

        // Try to register an invalid static callable
        $this->router->get('/invalid', [TestController::class, 'nonExistentStaticMethod']);
    }

    /**
     * @test
     */
    public function testArrayCallableWithMiddleware(): void
    {
        $middlewareCalled = false;
        $middleware = function ($req, $res, $next) use (&$middlewareCalled) {
            $middlewareCalled = true;
            return $next($req, $res);
        };

        $this->router->get('/with-middleware', [$this->controller, 'index'], [], $middleware);

        $route = $this->router->identify('GET', '/with-middleware');

        $this->assertNotNull($route);
        $this->assertIsCallable($route['handler']);
        $this->assertArrayHasKey('middlewares', $route);
        $this->assertCount(1, $route['middlewares']);
        $this->assertIsCallable($route['middlewares'][0]);
    }

    /**
     * @test
     */
    public function testRouterAddMethodDirectly(): void
    {
        // Test the Router::add method directly
        $this->router->add('GET', '/direct', [$this->controller, 'index']);

        $route = $this->router->identify('GET', '/direct');

        $this->assertNotNull($route);
        $this->assertEquals('/direct', $route['path']);
        $this->assertEquals('GET', $route['method']);
        $this->assertIsCallable($route['handler']);
        $this->assertIsArray($route['handler']);
    }

    /**
     * @test
     */
    public function testCallableValidation(): void
    {
        // These should all work
        $validCallables = [
            [$this->controller, 'index'],
            [TestController::class, 'staticMethod'],
            function () {
                return 'test';
            },
            'trim' // Built-in function
        ];

        foreach ($validCallables as $i => $callable) {
            $this->assertTrue(is_callable($callable), "Callable {$i} should be valid");
        }

        // These should fail
        $invalidCallables = [
            [$this->controller, 'nonExistent'],
            [TestController::class, 'nonExistentStatic'],
            ['NonExistentClass', 'method'],
            'nonExistentFunction'
        ];

        foreach ($invalidCallables as $i => $callable) {
            $this->assertFalse(is_callable($callable), "Callable {$i} should be invalid");
        }
    }

    /**
     * @test
     */
    public function testRouteRegistrationPerformance(): void
    {
        // Skip performance test when Xdebug is active (coverage mode)
        if (extension_loaded('xdebug') && xdebug_is_debugger_active()) {
            $this->markTestSkipped('Performance test skipped when Xdebug is active');
        }

        $start = microtime(true);

        // Register 100 routes with array callables
        for ($i = 0; $i < 100; $i++) {
            $this->router->get("/test{$i}", [$this->controller, 'index']);
        }

        $end = microtime(true);
        $duration = ($end - $start) * 1000; // Convert to milliseconds

        // More relaxed timeout for slower systems, Docker, CI, or when profiling is active
        $isSlowEnvironment = extension_loaded('xdebug') ||
                           getenv('CI') === 'true' ||
                           getenv('GITHUB_ACTIONS') === 'true' ||
                           is_dir('/.dockerenv') ||
                           file_exists('/.dockerenv');

        $maxDuration = $isSlowEnvironment ? 60000 : 10000; // 60s for slow environments, 10s for fast
        $this->assertLessThan($maxDuration, $duration, "Route registration took too long: {$duration}ms");

        // Verify all routes were registered
        $this->assertCount(100, $this->router->getRoutes());
    }
}
