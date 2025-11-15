<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Tests\Integration\Routing\ExampleController;

/**
 * Simple example demonstrating array callable usage
 */
class ArrayCallableExampleTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(__DIR__ . '/../../..');
        $this->setupExampleRoutes();
        $this->app->boot();
    }

    private function setupExampleRoutes(): void
    {
        $controller = new ExampleController();

        // ✅ Example 1: Instance method array callable
        $this->app->get('/health', [$controller, 'healthCheck']);

        // ✅ Example 2: Static method array callable
        $this->app->get('/api/info', [ExampleController::class, 'getApiInfo']);

        // ✅ Example 3: Array callable with parameters
        $this->app->get('/users/:id', [$controller, 'getUserById']);
    }

    /**
     * @test
     * Example usage: $app->get('/health', [$controller, 'healthCheck'])
     */
    public function testHealthCheckArrayCallable(): void
    {
        $request = new Request('GET', '/health', '/health');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertEquals('ok', $body['status']);
        $this->assertIsInt($body['timestamp']);
        $this->assertIsNumeric($body['memory_usage_mb']);
    }

    /**
     * @test
     * Example usage: $app->get('/api/info', [Controller::class, 'staticMethod'])
     */
    public function testStaticMethodArrayCallable(): void
    {
        $request = new Request('GET', '/api/info', '/api/info');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertEquals('1.0', $body['api_version']);
        $this->assertEquals('PivotPHP', $body['framework']);
        $this->assertEquals(Application::VERSION, $body['version']);
    }

    /**
     * @test
     * Example usage: $app->get('/users/:id', [$controller, 'getUserById'])
     */
    public function testParameterizedArrayCallable(): void
    {
        $request = new Request('GET', '/users/:id', '/users/12345');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertEquals('12345', $body['user_id']);
        $this->assertEquals('User 12345', $body['name']);
        $this->assertTrue($body['active']);
    }

    /**
     * @test
     * Performance comparison: closure vs array callable
     */
    /**
     * @group performance
     */
    public function testPerformanceComparison(): void
    {
        // Add closure route for comparison
        $this->app->get(
            '/closure-perf',
            function ($req, $res) {
                return $res->json(['type' => 'closure']);
            }
        );

        $iterations = 50;

        // Test array callable performance
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $request = new Request('GET', '/health', '/health');
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }
        $arrayCallableTime = (microtime(true) - $start) * 1000;

        // Test closure performance
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $request = new Request('GET', '/closure-perf', '/closure-perf');
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }
        $closureTime = (microtime(true) - $start) * 1000;

        // Performance difference should be reasonable
        // Note: Array callables can have higher overhead due to reflection, but should be manageable
        $overhead = (($arrayCallableTime - $closureTime) / $closureTime) * 100;

        // Allow higher overhead for array callables due to reflection overhead in testing environment
        // In production, this overhead is typically much lower due to opcode caching
        $maxOverhead = 1000; // 10x max overhead for testing environment

        $this->assertLessThan(
            $maxOverhead,
            $overhead,
            "Array callable overhead too high: {$overhead}% " .
            "(Array: {$arrayCallableTime}ms, Closure: {$closureTime}ms). " .
            "Note: High overhead in testing is normal due to reflection costs without opcode caching."
        );

        // Performance metrics stored in assertion message for CI/CD visibility
        // Results: Array Callable: {$arrayCallableTime}ms, Closure: {$closureTime}ms, Overhead: {$overhead}%
        $this->addToAssertionCount(1); // Mark test as having completed performance analysis
    }
}
