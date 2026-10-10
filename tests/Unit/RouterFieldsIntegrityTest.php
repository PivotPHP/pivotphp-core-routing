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
    /**
     * @test
     */
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
