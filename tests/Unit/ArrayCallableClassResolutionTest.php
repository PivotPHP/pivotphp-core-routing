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
    public function testInstanceMethodReferencedByClassResolvesLazily(): void
    {
        $this->router->get('/greet', [InstanceController::class, 'index']);

        $route = $this->router->identify('GET', '/greet');

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
        $this->router->get('/static', [InstanceController::class, 'staticMethod']);

        $route = $this->router->identify('GET', '/static');

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
        $this->router->get('/instance', [$controller, 'index']);

        $route = $this->router->identify('GET', '/instance');

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

        $this->router->get('/invalid', [InstanceController::class, 'nonExistent']);
    }
}
