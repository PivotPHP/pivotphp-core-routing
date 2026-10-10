<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Cobre a sintaxe de parâmetro com chaves ({id}, {id<constraint>}),
 * que deve ser equivalente à sintaxe com dois-pontos (:id).
 */
class BraceParameterTest extends TestCase
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
    public function testBraceParameterCompilesToNamedParameter(): void
    {
        $compiled = Router::compilePattern('/users/{id}');

        $this->assertEquals('#^/users/([^/]+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
        $this->assertEquals('[^/]+', $compiled['parameters'][0]['constraint']);
    }

    /**
     * @test
     */
    public function testBraceParameterWithConstraint(): void
    {
        $compiled = Router::compilePattern('/users/{id<\d+>}');

        $this->assertEquals('#^/users/(\d+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
        $this->assertEquals('\d+', $compiled['parameters'][0]['constraint']);
    }

    /**
     * @test
     */
    public function testBraceParameterWithShortcutConstraint(): void
    {
        $compiled = Router::compilePattern('/posts/{slug<slug>}');

        $this->assertEquals('#^/posts/([a-z0-9-]+)/?$#', $compiled['pattern']);
        $this->assertEquals('slug', $compiled['parameters'][0]['name']);
    }

    /**
     * @test
     */
    public function testBraceParameterMatchesAndExtractsParam(): void
    {
        $this->router->get('/users/{id}', function () {
            return 'user';
        });

        $route = $this->router->identify('GET', '/users/123');

        $this->assertNotNull($route);
        $this->assertEquals('/users/{id}', $route['path']);
        $this->assertEquals('123', $route['matched_params']['id']);
    }

    /**
     * @test
     */
    public function testMixedBraceAndColonParameters(): void
    {
        $this->router->get('/users/{userId}/posts/:postId', function () {
            return 'post';
        });

        $route = $this->router->identify('GET', '/users/456/posts/789');

        $this->assertNotNull($route);
        $this->assertEquals('456', $route['matched_params']['userId']);
        $this->assertEquals('789', $route['matched_params']['postId']);
    }

    /**
     * @test
     */
    public function testBraceParameterConstraintRejectsInvalid(): void
    {
        $this->router->get('/api/items/{id<\d+>}', function () {
            return 'item';
        });

        $this->assertNotNull($this->router->identify('GET', '/api/items/123'));
        $this->assertNull($this->router->identify('GET', '/api/items/abc'));
    }

    /**
     * @test
     */
    public function testColonSyntaxStillWorks(): void
    {
        $compiled = Router::compilePattern('/users/:id');

        $this->assertEquals('#^/users/([^/]+)/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexBlockStillTakesPrecedenceOverBraceParameter(): void
    {
        // {^...$} continua sendo tratado como regex block, não como parâmetro {id}
        $compiled = Router::compilePattern('/archive/{^(\d{4})/(\d{2})$}');

        $this->assertEquals('#^/archive/(\d{4})/(\d{2})/?$#', $compiled['pattern']);
        // Os grupos do regex block são registrados como parâmetros anônimos,
        // nunca como um parâmetro nomeado "id".
        foreach ($compiled['parameters'] as $param) {
            $this->assertStringStartsWith('_anonymous_', $param['name']);
        }
    }
}
