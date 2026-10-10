<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Semântica de métodos HTTP — SPEC-072.
 */
class HttpMethodSemanticsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }



    public function testHeadFallsBackToGetRoute(): void
    {
        $this->router->get('/page', static fn () => 'ok');

        $this->assertNotNull($this->router->identify('HEAD', '/page'));
        $this->assertNull($this->router->identify('HEAD', '/missing'));
    }

    public function testHeadPrefersExplicitHeadRoute(): void
    {
        $this->router->get('/page', static fn () => 'get');
        $this->router->head('/page', static fn () => 'head');

        $route = $this->router->identify('HEAD', '/page');
        $this->assertNotNull($route);
        $this->assertSame('HEAD', $route['method']);
    }

    public function testAllowedMethodsIncludesHeadWhenGet(): void
    {
        $this->router->get('/page', static fn () => 'ok');

        $methods = $this->router->allowedMethods('/page');
        $this->assertContains('GET', $methods);
        $this->assertContains('HEAD', $methods);
    }

    public function testAllowedMethodsForMultipleVerbs(): void
    {
        $this->router->get('/r', static fn () => 'g');
        $this->router->post('/r', static fn () => 'p');

        $methods = $this->router->allowedMethods('/r');
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('HEAD', $methods);
    }

    public function testAllowedMethodsEmptyForUnknownPath(): void
    {
        $this->assertSame([], $this->router->allowedMethods('/nope'));
    }
}
