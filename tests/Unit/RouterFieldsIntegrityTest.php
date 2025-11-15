<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use ReflectionClass;

class RouterFieldsIntegrityTest extends TestCase
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
    public function testRoutesByMethodHaveRequiredFields(): void
    {
        // Registra várias rotas
        Router::get(
            '/static',
            function () {
                return 'static';
            }
        );
        Router::get(
            '/users/:id<\d+>',
            function () {
                return 'user';
            }
        );
        Router::post(
            '/posts/:year<\d{4}>/:slug<[a-z0-9-]+>',
            function () {
                return 'post';
            }
        );

        // Usa reflexão para acessar $routesByMethod
        $reflection = new ReflectionClass(Router::class);
        $routesByMethodProperty = $reflection->getProperty('routesByMethod');
        $routesByMethodProperty->setAccessible(true);
        $routesByMethod = $routesByMethodProperty->getValue();

        $this->assertArrayHasKey('GET', $routesByMethod);
        $this->assertArrayHasKey('POST', $routesByMethod);

        // Verifica rotas GET
        foreach ($routesByMethod['GET'] as $routeKey => $route) {
            $this->assertArrayHasKey('pattern', $route, "Route {$routeKey} deve ter campo 'pattern'");
            $this->assertArrayHasKey('parameters', $route, "Route {$routeKey} deve ter campo 'parameters'");
            $this->assertArrayHasKey('has_parameters', $route, "Route {$routeKey} deve ter campo 'has_parameters'");
            $this->assertArrayHasKey('method', $route, "Route {$routeKey} deve ter campo 'method'");
            $this->assertArrayHasKey('path', $route, "Route {$routeKey} deve ter campo 'path'");
            $this->assertArrayHasKey('handler', $route, "Route {$routeKey} deve ter campo 'handler'");

            $this->assertIsArray($route['parameters'], "Campo 'parameters' deve ser array");
            $this->assertIsBool($route['has_parameters'], "Campo 'has_parameters' deve ser boolean");
        }

        // Verifica rotas POST
        foreach ($routesByMethod['POST'] as $routeKey => $route) {
            $this->assertArrayHasKey('pattern', $route, "Route {$routeKey} deve ter campo 'pattern'");
            $this->assertArrayHasKey('parameters', $route, "Route {$routeKey} deve ter campo 'parameters'");
            $this->assertArrayHasKey('has_parameters', $route, "Route {$routeKey} deve ter campo 'has_parameters'");

            $this->assertIsArray($route['parameters'], "Campo 'parameters' deve ser array");
            $this->assertIsBool($route['has_parameters'], "Campo 'has_parameters' deve ser boolean");
        }
    }

    /**
     * @test
     */
    public function testPreCompiledRoutesHaveRequiredFields(): void
    {
        Router::get(
            '/api/users/:id<\d+>/posts/:slug<[a-z0-9-]+>',
            function () {
                return 'user posts';
            }
        );

        // Usa reflexão para acessar $preCompiledRoutes
        $reflection = new ReflectionClass(Router::class);
        $preCompiledProperty = $reflection->getProperty('preCompiledRoutes');
        $preCompiledProperty->setAccessible(true);
        $preCompiledRoutes = $preCompiledProperty->getValue();

        $this->assertNotEmpty($preCompiledRoutes);

        foreach ($preCompiledRoutes as $routeKey => $route) {
            $this->assertArrayHasKey('pattern', $route, "PreCompiled route {$routeKey} deve ter 'pattern'");
            $this->assertArrayHasKey('parameters', $route, "PreCompiled route {$routeKey} deve ter 'parameters'");
            $this->assertArrayHasKey(
                'has_parameters',
                $route,
                "PreCompiled route {$routeKey} deve ter 'has_parameters'"
            );

            if ($route['has_parameters']) {
                $this->assertNotNull($route['pattern'], "Rota com parâmetros deve ter pattern não-null");
                $this->assertNotEmpty($route['parameters'], "Rota com parâmetros deve ter parameters não-vazio");
            }
        }
    }

    /**
     * @test
     */
    public function testDynamicRouteIdentificationWorks(): void
    {
        Router::get(
            '/complex/:category<[a-z]+>/items/:id<\d+>/details',
            function () {
                return 'complex route';
            }
        );

        // Força o uso do identifyOptimized
        $identified = Router::identify('GET', '/complex/electronics/items/123/details');

        $this->assertNotNull($identified, 'Rota dinâmica complexa deve ser identificada');
        $this->assertArrayHasKey('matched_params', $identified);
        $this->assertEquals('electronics', $identified['matched_params']['category']);
        $this->assertEquals('123', $identified['matched_params']['id']);
    }

    /**
     * @test
     */
    public function testStaticRouteIdentificationWorks(): void
    {
        Router::get(
            '/static/path/without/params',
            function () {
                return 'static route';
            }
        );

        $identified = Router::identify('GET', '/static/path/without/params');

        $this->assertNotNull($identified, 'Rota estática deve ser identificada');
        $this->assertEquals('/static/path/without/params', $identified['path']);
    }
}
