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
    protected function setUp(): void
    {
        parent::setUp();
        Router::clear();
    }

    public function testHeadFallsBackToGetRoute(): void
    {
        Router::get('/page', static fn () => 'ok');

        $this->assertNotNull(Router::identify('HEAD', '/page'));
        $this->assertNull(Router::identify('HEAD', '/missing'));
    }

    public function testHeadPrefersExplicitHeadRoute(): void
    {
        Router::get('/page', static fn () => 'get');
        Router::head('/page', static fn () => 'head');

        $route = Router::identify('HEAD', '/page');
        $this->assertNotNull($route);
        $this->assertSame('HEAD', $route['method']);
    }

    public function testAllowedMethodsIncludesHeadWhenGet(): void
    {
        Router::get('/page', static fn () => 'ok');

        $methods = Router::allowedMethods('/page');
        $this->assertContains('GET', $methods);
        $this->assertContains('HEAD', $methods);
    }

    public function testAllowedMethodsForMultipleVerbs(): void
    {
        Router::get('/r', static fn () => 'g');
        Router::post('/r', static fn () => 'p');

        $methods = Router::allowedMethods('/r');
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('HEAD', $methods);
    }

    public function testAllowedMethodsEmptyForUnknownPath(): void
    {
        $this->assertSame([], Router::allowedMethods('/nope'));
    }
}
