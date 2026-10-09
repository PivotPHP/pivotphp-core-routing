<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Comprehensive parameter routing tests
 */
class ParameterRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear();
    }

    protected function tearDown(): void
    {
        Router::clear();
    }

    /**
     * @test
     */
    public function testBasicParameterRouting(): void
    {
        Router::get(
            '/users/:id',
            function () {
                return 'user';
            }
        );

        $route = Router::identify('GET', '/users/123');

        $this->assertNotNull($route);
        $this->assertEquals('/users/:id', $route['path']);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('123', $route['matched_params']['id']);
    }

    /**
     * @test
     */
    public function testMultipleParameterRouting(): void
    {
        Router::get(
            '/users/:userId/posts/:postId',
            function () {
                return 'user post';
            }
        );

        $route = Router::identify('GET', '/users/456/posts/789');

        $this->assertNotNull($route);
        $this->assertEquals('/users/:userId/posts/:postId', $route['path']);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('456', $route['matched_params']['userId']);
        $this->assertEquals('789', $route['matched_params']['postId']);
    }

    /**
     * @test
     */
    public function testParameterWithConstraints(): void
    {
        Router::get(
            '/api/items/:id<\d+>',
            function () {
                return 'item';
            }
        );

        // Valid numeric parameter
        $route1 = Router::identify('GET', '/api/items/123');
        $this->assertNotNull($route1);
        $this->assertEquals('123', $route1['matched_params']['id']);

        // Invalid non-numeric parameter should not match
        $route2 = Router::identify('GET', '/api/items/abc');
        $this->assertNull($route2);
    }

    /**
     * @test
     */
    public function testComplexConstraints(): void
    {
        Router::get(
            '/files/:filename<[a-zA-Z0-9_-]+\.[a-z]{2,4}>',
            function () {
                return 'file';
            }
        );

        // Valid filename
        $route1 = Router::identify('GET', '/files/document.pdf');
        $this->assertNotNull($route1);
        $this->assertEquals('document.pdf', $route1['matched_params']['filename']);

        // Valid filename with underscores and dashes
        $route2 = Router::identify('GET', '/files/my_file-v2.txt');
        $this->assertNotNull($route2);
        $this->assertEquals('my_file-v2.txt', $route2['matched_params']['filename']);

        // Invalid filename (spaces not allowed)
        $route3 = Router::identify('GET', '/files/my file.txt');
        $this->assertNull($route3);
    }

    /**
     * @test
     */
    public function testSlugParameters(): void
    {
        Router::get(
            '/blog/:year<\d{4}>/:month<\d{2}>/:slug',
            function () {
                return 'blog post';
            }
        );

        $route = Router::identify('GET', '/blog/2024/12/my-awesome-post');

        $this->assertNotNull($route);
        $this->assertEquals('2024', $route['matched_params']['year']);
        $this->assertEquals('12', $route['matched_params']['month']);
        $this->assertEquals('my-awesome-post', $route['matched_params']['slug']);
    }

    /**
     * @test
     */
    public function testOptionalParameters(): void
    {
        // Parâmetros opcionais (:param?) não são suportados e devem ser rejeitados
        // com erro claro no registro (SPEC-026).
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional route parameters are not supported');

        Router::get(
            '/search/:query/:page?',
            function () {
                return 'search';
            }
        );
    }

    /**
     * @test
     */
    public function testPerformanceJsonSizeRoute(): void
    {
        // Create the specific route mentioned in the issue
        Router::get(
            '/performance/json/:size',
            function ($req, $res) {
                $size = $req->param('size');

            // Validate size parameter
                if (!in_array($size, ['small', 'medium', 'large'])) {
                    return $res->status(400)->json(['error' => 'Invalid size. Must be small, medium, or large']);
                }

            // Generate JSON data based on size
                $data = [];
                switch ($size) {
                    case 'small':
                        $data = array_fill(0, 10, ['id' => 1, 'name' => 'test']);
                        break;
                    case 'medium':
                        $data = array_fill(0, 100, ['id' => 1, 'name' => 'test', 'description' => 'medium test data']);
                        break;
                    case 'large':
                        $data = array_fill(
                            0,
                            1000,
                            [
                                'id' => 1,
                                'name' => 'test',
                                'description' => 'large test data',
                                'metadata' => ['created' => date('Y-m-d H:i:s')]
                            ]
                        );
                        break;
                }

                return $res->json(
                    [
                        'size' => $size,
                        'count' => count($data),
                        'data' => $data
                    ]
                );
            }
        );

        // Test valid sizes
        $route1 = Router::identify('GET', '/performance/json/small');
        $this->assertNotNull($route1);
        $this->assertEquals('small', $route1['matched_params']['size']);

        $route2 = Router::identify('GET', '/performance/json/medium');
        $this->assertNotNull($route2);
        $this->assertEquals('medium', $route2['matched_params']['size']);

        $route3 = Router::identify('GET', '/performance/json/large');
        $this->assertNotNull($route3);
        $this->assertEquals('large', $route3['matched_params']['size']);

        // Test invalid size
        $route4 = Router::identify('GET', '/performance/json/invalid');
        $this->assertNotNull($route4); // Route should match
        $this->assertEquals('invalid', $route4['matched_params']['size']); // But parameter should be 'invalid'
    }

    /**
     * @test
     */
    public function testNestedGroupsWithParameters(): void
    {
        Router::group(
            '/api/v1',
            function ($router) {
                $router->group(
                    '/users',  // ← Path relativo
                    function ($group) {
                        $group->get(
                            '/:id/profile',  // ← Path relativo
                            function () {
                                return 'user profile';
                            }
                        );

                        $group->get(
                            '/:id/posts/:postId',  // ← Path relativo
                            function () {
                                return 'user post';
                            }
                        );
                    }
                );
            }
        );

        $route1 = Router::identify('GET', '/api/v1/users/123/profile');
        $this->assertNotNull($route1);
        $this->assertEquals('123', $route1['matched_params']['id']);

        $route2 = Router::identify('GET', '/api/v1/users/456/posts/789');
        $this->assertNotNull($route2);
        $this->assertEquals('456', $route2['matched_params']['id']);
        $this->assertEquals('789', $route2['matched_params']['postId']);
    }

    /**
     * @test
     */
    public function testParameterWithSpecialCharacters(): void
    {
        Router::get(
            '/encode/:data',
            function () {
                return 'encoded';
            }
        );

        // Test URL encoded parameters
        $route = Router::identify('GET', '/encode/hello%20world');
        $this->assertNotNull($route);
        $this->assertEquals('hello%20world', $route['matched_params']['data']);
    }

    /**
     * @test
     */
    public function testNumericConstraintValidation(): void
    {
        Router::get(
            '/pages/:page<\d+>',
            function () {
                return 'page';
            }
        );

        // Valid numeric
        $route1 = Router::identify('GET', '/pages/42');
        $this->assertNotNull($route1);
        $this->assertEquals('42', $route1['matched_params']['page']);

        // Invalid - letters
        $route2 = Router::identify('GET', '/pages/abc');
        $this->assertNull($route2);

        // Invalid - mixed
        $route3 = Router::identify('GET', '/pages/42abc');
        $this->assertNull($route3);
    }

    /**
     * @test
     */
    public function testRouteParameterExtraction(): void
    {
        Router::get(
            '/products/:category/:id<\d+>',
            function () {
                return 'product';
            }
        );

        $route = Router::identify('GET', '/products/electronics/12345');

        $this->assertNotNull($route);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertArrayHasKey('category', $route['matched_params']);
        $this->assertArrayHasKey('id', $route['matched_params']);
        $this->assertEquals('electronics', $route['matched_params']['category']);
        $this->assertEquals('12345', $route['matched_params']['id']);
    }

    /**
     * @test
     */
    public function testConflictingRoutes(): void
    {
        // Register conflicting routes - specific should match before generic
        Router::get(
            '/users/admin',
            function () {
                return 'admin';
            }
        );

        Router::get(
            '/users/:id',
            function () {
                return 'user';
            }
        );

        // Static route should match first
        $route1 = Router::identify('GET', '/users/admin');
        $this->assertNotNull($route1);
        $this->assertEquals('/users/admin', $route1['path']);

        // Parameter route should match for other values
        $route2 = Router::identify('GET', '/users/123');
        $this->assertNotNull($route2);
        $this->assertEquals('/users/:id', $route2['path']);
        $this->assertEquals('123', $route2['matched_params']['id']);
    }
}
