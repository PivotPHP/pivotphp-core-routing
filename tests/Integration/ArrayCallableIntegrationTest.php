<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Tests\Integration\Routing\HealthController;

/**
 * Integration tests for array callable functionality with full Application
 */
class ArrayCallableIntegrationTest extends TestCase
{
    private Application $app;
    private HealthController $healthController;

    protected function setUp(): void
    {
        $this->app = new Application(__DIR__ . '/../../..');
        $this->healthController = new HealthController();
        $this->setupRoutes();
        $this->app->boot();
    }

    private function setupRoutes(): void
    {
        // Test instance method array callable (unique paths)
        $uniqueId = substr(md5(__METHOD__), 0, 8);
        $this->app->get("/health-{$uniqueId}", [$this->healthController, 'healthCheck']);

        // Test static method array callable
        $this->app->get("/health-{$uniqueId}/static", [HealthController::class, 'staticHealthCheck']);

        // Test with parameters
        $this->app->get("/users/:userId/health-{$uniqueId}", [$this->healthController, 'getUserHealth']);

        // Test in groups using Router directly
        $this->app->get("/api/v1/status-{$uniqueId}", [$this->healthController, 'healthCheck']);

        // Mix with closures for comparison
        $this->app->get(
            '/closure-test',
            function ($req, $res) {
                return $res->json(['type' => 'closure']);
            }
        );
    }

    /**
     * @test
     */
    public function testInstanceMethodArrayCallable(): void
    {
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $request = new Request('GET', "/health-{$uniqueId}", "/health-{$uniqueId}");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertIsArray($body);
        $this->assertEquals('ok', $body['status']);
        $this->assertArrayHasKey('timestamp', $body);
        $this->assertArrayHasKey('memory_usage_mb', $body);
        $this->assertArrayHasKey('version', $body);
        $this->assertEquals(Application::VERSION, $body['version']);
    }

    /**
     * @test
     */
    public function testStaticMethodArrayCallable(): void
    {
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $request = new Request('GET', "/health-{$uniqueId}/static", "/health-{$uniqueId}/static");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertIsArray($body);
        $this->assertEquals('static_ok', $body['status']);
        $this->assertEquals('static', $body['method']);
    }

    /**
     * @test
     */
    public function testArrayCallableWithParameters(): void
    {
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $request = new Request('GET', "/users/:userId/health-{$uniqueId}", "/users/12345/health-{$uniqueId}");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertIsArray($body);
        $this->assertEquals('12345', $body['user_id']);
        $this->assertEquals('healthy', $body['status']);
        $this->assertArrayHasKey('checked_at', $body);
    }

    /**
     * @test
     */
    public function testArrayCallableInGroup(): void
    {
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $request = new Request('GET', "/api/v1/status-{$uniqueId}", "/api/v1/status-{$uniqueId}");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $response->getBody();
        $body = json_decode(is_string($responseBody) ? $responseBody : $responseBody->__toString(), true);
        $this->assertIsArray($body);
        $this->assertEquals('ok', $body['status']);
    }

    /**
     * @test
     */
    public function testClosureVsArrayCallableComparison(): void
    {
        // Test closure
        $closureRequest = new Request('GET', '/closure-test', '/closure-test');
        $closureResponse = $this->app->handle($closureRequest);

        // Test array callable
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $arrayRequest = new Request('GET', "/health-{$uniqueId}", "/health-{$uniqueId}");
        $arrayResponse = $this->app->handle($arrayRequest);

        // Both should work
        $this->assertEquals(200, $closureResponse->getStatusCode());
        $this->assertEquals(200, $arrayResponse->getStatusCode());

        $closureResponseBody = $closureResponse->getBody();
        $closureBody = json_decode(
            is_string($closureResponseBody) ? $closureResponseBody : $closureResponseBody->__toString(),
            true
        );
        $arrayResponseBody = $arrayResponse->getBody();
        $arrayBody = json_decode(
            is_string($arrayResponseBody) ? $arrayResponseBody : $arrayResponseBody->__toString(),
            true
        );

        $this->assertEquals('closure', $closureBody['type']);
        $this->assertEquals('ok', $arrayBody['status']);
    }

    /**
     * @test
     */
    public function testInvalidArrayCallableFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // This should fail during route registration
        $this->app->get('/invalid', [$this->healthController, 'nonExistentMethod']);
    }

    /**
     * @test
     */
    public function testArrayCallablePerformance(): void
    {
        $start = microtime(true);

        // Make multiple requests to array callable route
        for ($i = 0; $i < 10; $i++) {
            $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
            $request = new Request('GET', "/health-{$uniqueId}", "/health-{$uniqueId}");
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }

        $end = microtime(true);
        $duration = ($end - $start) * 1000; // Convert to milliseconds

        // Should be reasonably fast (less than 100ms for 10 requests)
        $this->assertLessThan(100, $duration, "Array callable routing took too long: {$duration}ms");
    }

    /**
     * @test
     */
    public function testMultipleControllersAndMethods(): void
    {
        // Create another controller
        $anotherController = new class {
            public function testMethod($req, $res)
            {
                return $res->json(['controller' => 'another']);
            }
        };

        // Register route with different controller
        $this->app->get('/another', [$anotherController, 'testMethod']);

        // Test original controller
        $uniqueId = substr(md5(__CLASS__ . '::setupRoutes'), 0, 8);
        $healthRequest = new Request('GET', "/health-{$uniqueId}", "/health-{$uniqueId}");
        $healthResponse = $this->app->handle($healthRequest);

        // Test new controller
        $anotherRequest = new Request('GET', '/another', '/another');
        $anotherResponse = $this->app->handle($anotherRequest);

        $this->assertEquals(200, $healthResponse->getStatusCode());
        $this->assertEquals(200, $anotherResponse->getStatusCode());

        $healthResponseBody = $healthResponse->getBody();
        $healthBody = json_decode(
            is_string($healthResponseBody) ? $healthResponseBody : $healthResponseBody->__toString(),
            true
        );
        $anotherResponseBody = $anotherResponse->getBody();
        $anotherBody = json_decode(
            is_string($anotherResponseBody) ? $anotherResponseBody : $anotherResponseBody->__toString(),
            true
        );

        $this->assertEquals('ok', $healthBody['status']);
        $this->assertEquals('another', $anotherBody['controller']);
    }

    /**
     * @test
     */
    public function testErrorHandlingInArrayCallable(): void
    {
        // Create controller that throws exception
        $errorController = new class {
            public function throwError($req, $res)
            {
                throw new \Exception('Test error from array callable');
            }
        };

        $this->app->get('/error-test', [$errorController, 'throwError']);

        $request = new Request('GET', '/error-test', '/error-test');

        // The application should handle the exception (might return 500)
        $response = $this->app->handle($request);

        // Status should be 500 or the error should be handled gracefully
        $this->assertContains($response->getStatusCode(), [500, 400, 404]);
    }

    /**
     * @test
     */
    public function testResponseTypesFromArrayCallable(): void
    {
        // Test different response types
        $responseController = new class {
            public function jsonResponse($req, $res)
            {
                return $res->json(['type' => 'json']);
            }

            public function textResponse($req, $res)
            {
                return $res->send('plain text');
            }

            public function statusResponse($req, $res)
            {
                return $res->status(201)->json(['created' => true]);
            }
        };

        $this->app->get('/json', [$responseController, 'jsonResponse']);
        $this->app->get('/text', [$responseController, 'textResponse']);
        $this->app->get('/status', [$responseController, 'statusResponse']);

        // Test JSON response
        $jsonRequest = new Request('GET', '/json', '/json');
        $jsonResponse = $this->app->handle($jsonRequest);
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $jsonResponseBody = $jsonResponse->getBody();
        $jsonBody = json_decode(
            is_string($jsonResponseBody) ? $jsonResponseBody : $jsonResponseBody->__toString(),
            true
        );
        $this->assertEquals('json', $jsonBody['type']);

        // Test text response
        $textRequest = new Request('GET', '/text', '/text');
        $textResponse = $this->app->handle($textRequest);
        $this->assertEquals(200, $textResponse->getStatusCode());
        $textResponseBody = $textResponse->getBody();
        $this->assertEquals(
            'plain text',
            is_string($textResponseBody) ? $textResponseBody : $textResponseBody->__toString()
        );

        // Test status response
        $statusRequest = new Request('GET', '/status', '/status');
        $statusResponse = $this->app->handle($statusRequest);
        $this->assertEquals(201, $statusResponse->getStatusCode());
        $statusResponseBody = $statusResponse->getBody();
        $statusBody = json_decode(
            is_string($statusResponseBody) ? $statusResponseBody : $statusResponseBody->__toString(),
            true
        );
        $this->assertTrue($statusBody['created']);
    }
}
