<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouteCacheNonGreedyTest extends TestCase
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
    public function testNonGreedyRegexWithAdjacentBlocks(): void
    {
        // Este padrão testa se o regex é greedy ou não
        $compiled = Router::compilePattern('/api/{^v(\d+)$}/users/{^(\d+)$}/profile');

        // Com regex não-greedy, deve processar cada bloco separadamente
        $this->assertEquals('#^/api/v(\d+)/users/(\d+)/profile/?$#', $compiled['pattern']);

        // Deve ter 2 grupos de captura
        $this->assertCount(2, $compiled['parameters']);
    }

    /**
     * @test
     */
    public function testNonGreedyRegexWithComplexPattern(): void
    {
        // Padrão mais complexo com múltiplos blocos
        $compiled = Router::compilePattern('/data/{^([a-z]+)$}/items/{^(\d{4}-\d{2})$}/details');

        $this->assertEquals('#^/data/([a-z]+)/items/(\d{4}-\d{2})/details/?$#', $compiled['pattern']);
        $this->assertCount(2, $compiled['parameters']);
    }

    /**
     * @test
     */
    public function testNonGreedyWithMixedSyntax(): void
    {
        // Mistura blocos regex com parâmetros normais
        $compiled = Router::compilePattern('/files/{^(docs|images)$}/:name<[a-z0-9-]+>/{^\\.(pdf|jpg)$}');

        // Deve processar os blocos regex e o parâmetro separadamente
        $this->assertStringContainsString('(docs|images)', $compiled['pattern']);
        $this->assertStringContainsString('([a-z0-9-]+)', $compiled['pattern']);
        $this->assertStringContainsString('\\.(pdf|jpg)', $compiled['pattern']);

        // Deve ter 3 grupos de captura (2 dos blocos regex + 1 do parâmetro)
        $this->assertCount(3, $compiled['parameters']); // Todos os grupos de captura são contados
    }

    /**
     * @test
     */
    public function testNonGreedyDoesNotAffectSingleBlocks(): void
    {
        // Testa que blocos únicos ainda funcionam corretamente
        $compiled = Router::compilePattern('/archive/{^(\d{4})/(\d{2})/(.+)$}');

        $this->assertEquals('#^/archive/(\d{4})/(\d{2})/(.+)/?$#', $compiled['pattern']);

        // Testa que funciona na prática
        $this->assertMatchesRegularExpression($compiled['pattern'], '/archive/2025/07/my-post');

        preg_match($compiled['pattern'], '/archive/2025/07/my-post', $matches);
        $this->assertEquals('2025', $matches[1]);
        $this->assertEquals('07', $matches[2]);
        $this->assertEquals('my-post', $matches[3]);
    }

    /**
     * @test
     */
    public function testNonGreedyWithNestedBraces(): void
    {
        // Testa padrão que contém chaves internas (quantifiers)
        $compiled = Router::compilePattern('/test/{^([a-z]{3,5})$}/data/{^(\d{1,4})$}');

        $this->assertEquals('#^/test/([a-z]{3,5})/data/(\d{1,4})/?$#', $compiled['pattern']);

        // Verifica que funciona corretamente
        $this->assertMatchesRegularExpression($compiled['pattern'], '/test/hello/data/123');
        // 'hi' tem apenas 2 caracteres, deve falhar na validação {3,5}
        $this->assertDoesNotMatchRegularExpression($compiled['pattern'], '/test/hi/data/123');
    }

    /**
     * @test
     */
    public function testGreedyProblematicCase(): void
    {
        // Este caso seria problemático com regex greedy
        // Com greedy: capturaria tudo de {primeiro} até {ultimo}
        // Com non-greedy: processa cada bloco separadamente
        $compiled = Router::compilePattern('/path/{^first(\d+)$}/middle/{^second(\d+)$}/end');

        $expected = '#^/path/first(\d+)/middle/second(\d+)/end/?$#';
        $this->assertEquals($expected, $compiled['pattern']);

        // Testa funcionamento
        $this->assertMatchesRegularExpression($compiled['pattern'], '/path/first123/middle/second456/end');

        preg_match($compiled['pattern'], '/path/first123/middle/second456/end', $matches);
        $this->assertEquals('123', $matches[1]);
        $this->assertEquals('456', $matches[2]);
    }
}
