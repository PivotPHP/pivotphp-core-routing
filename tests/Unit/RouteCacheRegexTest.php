<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouteCacheRegexTest extends TestCase
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
    public function testBackwardCompatibilityWithSimpleParameters(): void
    {
        // Testa que a sintaxe antiga continua funcionando
        $compiled = Router::compilePattern('/users/:id');

        $this->assertArrayHasKey('pattern', $compiled);
        $this->assertArrayHasKey('parameters', $compiled);
        $this->assertEquals('#^/users/([^/]+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
    }

    /**
     * @test
     */
    public function testConstrainedParametersWithDigits(): void
    {
        $compiled = Router::compilePattern('/users/:id<\d+>');

        $this->assertEquals('#^/users/(\d+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
        $this->assertEquals('\d+', $compiled['parameters'][0]['constraint']);
    }

    /**
     * @test
     */
    public function testMultipleConstrainedParameters(): void
    {
        $compiled = Router::compilePattern('/posts/:year<\d{4}>/:month<\d{2}>/:slug<[a-z0-9-]+>');

        $this->assertEquals('#^/posts/(\d{4})/(\d{2})/([a-z0-9-]+)/?$#', $compiled['pattern']);
        $this->assertCount(3, $compiled['parameters']);

        $this->assertEquals('year', $compiled['parameters'][0]['name']);
        $this->assertEquals('\d{4}', $compiled['parameters'][0]['constraint']);

        $this->assertEquals('month', $compiled['parameters'][1]['name']);
        $this->assertEquals('\d{2}', $compiled['parameters'][1]['constraint']);

        $this->assertEquals('slug', $compiled['parameters'][2]['name']);
        $this->assertEquals('[a-z0-9-]+', $compiled['parameters'][2]['constraint']);
    }

    /**
     * @test
     */
    public function testConstraintShortcuts(): void
    {
        $shortcuts = [
            'int' => '\d+',
            'slug' => '[a-z0-9-]+',
            'alpha' => '[a-zA-Z]+',
            'alnum' => '[a-zA-Z0-9]+',
            'uuid' => '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
            'date' => '\d{4}-\d{2}-\d{2}',
            'year' => '\d{4}',
            'month' => '\d{2}',
            'day' => '\d{2}'
        ];

        foreach ($shortcuts as $shortcut => $expectedRegex) {
            $compiled = Router::compilePattern("/test/:param<{$shortcut}>");
            $this->assertEquals("#^/test/({$expectedRegex})/?$#", $compiled['pattern']);
        }
    }

    /**
     * @test
     */
    public function testFullRegexSyntax(): void
    {
        $compiled = Router::compilePattern('/archive/{^(\d{4})/(\d{2})/(.+)$}');

        // As âncoras ^ e $ devem ser removidas do regex fornecido
        $this->assertEquals('#^/archive/(\d{4})/(\d{2})/(.+)/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testMixedConstraintAndRegexSyntax(): void
    {
        $compiled = Router::compilePattern('/api/:version<v\d+>/{^/(.+\.json)$}');

        $this->assertStringContainsString('(v\d+)', $compiled['pattern']);
        $this->assertStringContainsString('/(.+\.json)$', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testStaticRouteDetection(): void
    {
        // Rotas estáticas não devem ter pattern
        $compiled = Router::compilePattern('/api/users');

        $this->assertNull($compiled['pattern']);
        $this->assertEmpty($compiled['parameters']);
        $this->assertTrue(Router::isStaticRoute('/api/users'));
    }

    /**
     * @test
     */
    public function testDynamicRouteDetection(): void
    {
        $this->assertFalse(Router::isStaticRoute('/users/:id'));
        $this->assertFalse(Router::isStaticRoute('/users/:id<\d+>'));
        $this->assertFalse(Router::isStaticRoute('/files/{^(.+)$}'));
    }

    /**
     * @test
     */
    public function testReDoSProtection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsafe regex pattern detected');

        // Padrão perigoso com nested quantifiers
        Router::compilePattern('/test/:param<(\w+)*\w*>');
    }

    /**
     * @test
     */
    public function testReDoSProtectionForNestedQuantifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Router::compilePattern('/test/:param<(.+)+>');
    }

    /**
     * @test
     */
    public function testReDoSProtectionForExcessiveAlternations(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $pattern = '/test/:param<a|b|c|d|e|f|g|h|i|j|k|l>';
        Router::compilePattern($pattern);
    }

    /**
     * @test
     */
    public function testReDoSProtectionForLongPatterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $longPattern = str_repeat('a', 201); // Mais de 200 caracteres
        Router::compilePattern("/test/:param<{$longPattern}>");
    }

    /**
     * @test
     */
    /**
     * @test
     */
    public function testParameterPositioning(): void
    {
        $compiled = Router::compilePattern('/api/:version<v\d+>/users/:id<\d+>/posts/:slug<slug>');

        $this->assertEquals('version', $compiled['parameters'][0]['name']);
        $this->assertEquals(0, $compiled['parameters'][0]['position']);

        $this->assertEquals('id', $compiled['parameters'][1]['name']);
        $this->assertEquals(1, $compiled['parameters'][1]['position']);

        $this->assertEquals('slug', $compiled['parameters'][2]['name']);
        $this->assertEquals(2, $compiled['parameters'][2]['position']);
    }

    /**
     * @test
     */
    public function testComplexFileExtensionPattern(): void
    {
        $compiled = Router::compilePattern('/files/:filename<[\w-]+>.:ext<jpg|png|gif|webp>');

        $this->assertEquals('#^/files/([\w-]+)\.(jpg|png|gif|webp)/?$#', $compiled['pattern']);
        $this->assertCount(2, $compiled['parameters']);
        $this->assertEquals('filename', $compiled['parameters'][0]['name']);
        $this->assertEquals('ext', $compiled['parameters'][1]['name']);
    }

    /**
     * @test
     */
    public function testEmailLikePattern(): void
    {
        $compiled = Router::compilePattern('/contact/:email<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+>');

        $this->assertStringContainsString('([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+)', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testISBNPattern(): void
    {
        $compiled = Router::compilePattern('/books/:isbn<\d{3}-\d{10}>');

        $this->assertEquals('#^/books/(\d{3}-\d{10})/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testOptionalTrailingSlash(): void
    {
        $compiled1 = Router::compilePattern('/users/:id<\d+>');
        $compiled2 = Router::compilePattern('/users/:id<\d+>/');

        // Ambos devem produzir o mesmo pattern com /? opcional no final
        $this->assertEquals($compiled1['pattern'], $compiled2['pattern']);
        $this->assertStringEndsWith('/?$#', $compiled1['pattern']);
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
    public function testInvalidRegexDetection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Regex inválido (parênteses não balanceados)
        Router::compilePattern('/test/:param<[abc(>');
    }

    /**
     * @test
     */
    public function testMixedParameterTypes(): void
    {
        // Mistura de parâmetros com e sem constraints
        $compiled = Router::compilePattern('/api/:version/users/:id<\d+>/profile/:section');

        $this->assertCount(3, $compiled['parameters']);
        $this->assertEquals('[^/]+', $compiled['parameters'][0]['constraint']); // Default
        $this->assertEquals('\d+', $compiled['parameters'][1]['constraint']);
        $this->assertEquals('[^/]+', $compiled['parameters'][2]['constraint']); // Default
    }
}
