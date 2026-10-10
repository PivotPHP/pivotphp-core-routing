<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use ReflectionClass;

class RouterFieldsIntegrityTest extends TestCase
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
    /**
     * @test
     */
    /**
     * @test
     */
    public function testDynamicRouteIdentificationWorks(): void
    {
        $this->router->get(
            '/complex/:category<[a-z]+>/items/:id<\d+>/details',
            function () {
                return 'complex route';
            }
        );

        // Força o uso do identifyOptimized
        $identified = $this->router->identify('GET', '/complex/electronics/items/123/details');

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
        $this->router->get(
            '/static/path/without/params',
            function () {
                return 'static route';
            }
        );

        $identified = $this->router->identify('GET', '/static/path/without/params');

        $this->assertNotNull($identified, 'Rota estática deve ser identificada');
        $this->assertEquals('/static/path/without/params', $identified['path']);
    }
}
