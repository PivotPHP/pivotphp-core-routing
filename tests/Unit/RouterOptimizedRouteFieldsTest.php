<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouterOptimizedRouteFieldsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->clear();
    }

    protected function tearDown(): void
    {
        $this->router->clear();
    }

    /**
     * @test
     */
    public function testOptimizedRouteIncludesRequiredFields(): void
    {
        // Registra uma rota com parâmetros
        $this->router->get(
            '/users/:id<\d+>',
            function () {
                return 'user';
            }
        );

        // Obtém as rotas registradas para inspecionar
        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);

        $route = $routes[0];

        // Verifica se os campos necessários estão presentes
        $this->assertArrayHasKey('pattern', $route, 'Route deve ter campo pattern');
        $this->assertArrayHasKey('parameters', $route, 'Route deve ter campo parameters');
        $this->assertArrayHasKey('has_parameters', $route, 'Route deve ter campo has_parameters');

        // Verifica se os valores estão corretos
        $this->assertNotNull($route['pattern'], 'Pattern não deve ser null');
        $this->assertIsArray($route['parameters'], 'Parameters deve ser array');
        $this->assertTrue($route['has_parameters'], 'has_parameters deve ser true para rota com parâmetros');

        // Verifica se o pattern está correto
        $this->assertEquals('#^/users/(\d+)/?$#', $route['pattern']);

        // Verifica se os parâmetros estão corretos
        $this->assertCount(1, $route['parameters']);
        $this->assertEquals('id', $route['parameters'][0]['name']);
        $this->assertEquals('\d+', $route['parameters'][0]['constraint']);
    }

    /**
     * @test
     */
    public function testIdentifyOptimizedUsesCompiledFields(): void
    {
        $this->router->get(
            '/posts/:year<\d{4}>/:slug<[a-z0-9-]+>',
            function () {
                return 'post';
            }
        );

        // Testa que identifyOptimized consegue encontrar a rota
        $identified = $this->router->identify('GET', '/posts/2025/hello-world');

        $this->assertNotNull($identified, 'Route deveria ser identificada');
        $this->assertEquals('/posts/:year<\d{4}>/:slug<[a-z0-9-]+>', $identified['path']);
        $this->assertArrayHasKey('matched_params', $identified);
        $this->assertEquals('2025', $identified['matched_params']['year']);
        $this->assertEquals('hello-world', $identified['matched_params']['slug']);
    }

    /**
     * @test
     */
    public function testStaticRouteHasCorrectFields(): void
    {
        $this->router->get(
            '/static/route',
            function () {
                return 'static';
            }
        );

        $routes = $this->router->getRoutes();
        $route = $routes[0];

        // Rota estática deve ter has_parameters = false
        $this->assertArrayHasKey('has_parameters', $route);
        $this->assertFalse($route['has_parameters'], 'Rota estática deve ter has_parameters = false');

        // Pattern pode ser null para rotas estáticas
        $this->assertArrayHasKey('pattern', $route);
        $this->assertArrayHasKey('parameters', $route);
        $this->assertEmpty($route['parameters'], 'Rota estática deve ter parameters vazio');
    }

    /**
     * @test
     */
    public function testRoutesByMethodHaveCorrectFields(): void
    {
        $this->router->get(
            '/api/users/:id<\d+>',
            function () {
                return 'get user';
            }
        );

        $this->router->post(
            '/api/users',
            function () {
                return 'create user';
            }
        );

        // Testa GET route
        $getRoute = $this->router->identify('GET', '/api/users/123');
        $this->assertNotNull($getRoute);
        $this->assertArrayHasKey('pattern', $getRoute);
        $this->assertArrayHasKey('parameters', $getRoute);
        $this->assertArrayHasKey('has_parameters', $getRoute);
        $this->assertTrue($getRoute['has_parameters']);

        // Testa POST route
        $postRoute = $this->router->identify('POST', '/api/users');
        $this->assertNotNull($postRoute);
        $this->assertArrayHasKey('pattern', $postRoute);
        $this->assertArrayHasKey('parameters', $postRoute);
        $this->assertArrayHasKey('has_parameters', $postRoute);
        $this->assertFalse($postRoute['has_parameters']);
    }
}
