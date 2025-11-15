<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouterGroupConstraintTest extends TestCase
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
    public function testGroupRoutesWithConstraints(): void
    {
        // Registra rotas no grupo /api
        Router::group(
            '/api',
            function () {
                Router::get(
                    '/users/:id<\d+>',
                    function () {
                        return 'user by id';
                    }
                );

                Router::get(
                    '/posts/:year<\d{4}>/:month<\d{2}>/:slug<[a-z0-9-]+>',
                    function () {
                        return 'post by date and slug';
                    }
                );

                Router::get(
                    '/products/:sku<[A-Z]{3}-\d{4}>',
                    function () {
                        return 'product by sku';
                    }
                );
            }
        );

        // Testa rota com constraint de dígitos
        $route1 = Router::identifyByGroup('GET', '/api/users/123');
        $this->assertNotNull($route1);
        $this->assertEquals('/api/users/:id<\d+>', $route1['path']);
        $this->assertArrayHasKey('matched_params', $route1);
        $this->assertEquals('123', $route1['matched_params']['id']);

        // Testa que NÃO faz match com string (constraints SÃO aplicadas)
        $route2 = Router::identifyByGroup('GET', '/api/users/abc');
        $this->assertNull($route2); // Deve retornar null pois 'abc' não corresponde a \d+

        // Testa rota com múltiplos parâmetros e constraints
        $route3 = Router::identifyByGroup('GET', '/api/posts/2025/07/hello-world');
        $this->assertNotNull($route3);
        $this->assertEquals('/api/posts/:year<\d{4}>/:month<\d{2}>/:slug<[a-z0-9-]+>', $route3['path']);
        $this->assertArrayHasKey('matched_params', $route3);
        $this->assertEquals('2025', $route3['matched_params']['year']);
        $this->assertEquals('07', $route3['matched_params']['month']);
        $this->assertEquals('hello-world', $route3['matched_params']['slug']);

        // Testa rota com pattern de SKU
        $route4 = Router::identifyByGroup('GET', '/api/products/ABC-1234');
        $this->assertNotNull($route4);
        $this->assertEquals('/api/products/:sku<[A-Z]{3}-\d{4}>', $route4['path']);
        $this->assertArrayHasKey('matched_params', $route4);
        $this->assertEquals('ABC-1234', $route4['matched_params']['sku']);

        // Testa que NÃO faz match com formato inválido (constraints SÃO aplicadas)
        $route5 = Router::identifyByGroup('GET', '/api/products/abc-1234');
        $this->assertNull($route5); // Deve retornar null pois 'abc' não é uppercase
    }

    /**
     * @test
     */
    public function testNestedGroupsWithConstraints(): void
    {
        // Grupos aninhados - agora com paths relativos corretos
        Router::group(
            '/v1',
            function () {
                Router::group(
                    '/admin',  // ← Path relativo ao grupo pai
                    function () {
                        Router::get(
                            '/users/:id<\d+>/edit',  // ← Path relativo ao grupo pai
                            function () {
                                return 'edit user';
                            }
                        );
                    }
                );
            }
        );

        $route = Router::identifyByGroup('GET', '/v1/admin/users/456/edit');
        $this->assertNotNull($route);
        $this->assertEquals('/v1/admin/users/:id<\d+>/edit', $route['path']);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('456', $route['matched_params']['id']);
    }

    /**
     * @test
     */
    public function testGroupIdentificationUsesCompiledPatterns(): void
    {
        Router::group(
            '/test',
            function () {
                Router::get(
                    '/test/item/:id<\d+>',
                    function () {
                        return 'item';
                    }
                );
            }
        );

        // Verifica que a rota tem os campos compilados
        $routes = Router::getRoutes();
        $lastRoute = end($routes);

        $this->assertArrayHasKey('pattern', $lastRoute);
        $this->assertArrayHasKey('parameters', $lastRoute);
        $this->assertArrayHasKey('has_parameters', $lastRoute);
        $this->assertTrue($lastRoute['has_parameters']);

        // Verifica que identifyByGroup funciona
        $identified = Router::identifyByGroup('GET', '/test/item/999');
        $this->assertNotNull($identified);
        $this->assertEquals('999', $identified['matched_params']['id']);
    }
}
