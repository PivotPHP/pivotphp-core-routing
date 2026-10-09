<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Cobre a resolução de array callables [Classe::class, 'método'] com métodos
 * de instância (SPEC-003), que anteriormente lançava InvalidArgumentException.
 */
class ArrayCallableClassResolutionTest extends TestCase
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
    public function testInstanceMethodReferencedByClassResolvesLazily(): void
    {
        Router::get('/greet', [InstanceController::class, 'index']);

        $route = Router::identify('GET', '/greet');

        $this->assertNotNull($route);
        $this->assertInstanceOf(\Closure::class, $route['handler']);

        // A chamada instancia a classe e executa o método de instância.
        $this->assertSame('index called with 2 args', ($route['handler'])('req', 'res'));
    }

    /**
     * @test
     */
    public function testStaticMethodReferencedByClassRemainsArray(): void
    {
        Router::get('/static', [InstanceController::class, 'staticMethod']);

        $route = Router::identify('GET', '/static');

        $this->assertNotNull($route);
        $this->assertIsArray($route['handler']);
        $this->assertSame([InstanceController::class, 'staticMethod'], $route['handler']);
    }

    /**
     * @test
     */
    public function testInstanceArrayCallableStillWorks(): void
    {
        $controller = new InstanceController();
        Router::get('/instance', [$controller, 'index']);

        $route = Router::identify('GET', '/instance');

        $this->assertNotNull($route);
        $this->assertIsArray($route['handler']);
        $this->assertSame($controller, $route['handler'][0]);
    }

    /**
     * @test
     */
    public function testNonExistentInstanceMethodStillThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        Router::get('/invalid', [InstanceController::class, 'nonExistent']);
    }
}
