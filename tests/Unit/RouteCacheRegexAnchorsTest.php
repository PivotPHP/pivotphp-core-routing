<?php

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

class RouteCacheRegexAnchorsTest extends TestCase
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
    public function testRegexWithBothAnchors(): void
    {
        $compiled = Router::compilePattern('/api/{^v(\d+)/users/(\d+)$}');

        // Deve remover ^ e $ do regex fornecido
        $this->assertEquals('#^/api/v(\d+)/users/(\d+)/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexWithOnlyStartAnchor(): void
    {
        $compiled = Router::compilePattern('/test/{^foo/bar/(\w+)}');

        // Deve remover apenas ^
        $this->assertEquals('#^/test/foo/bar/(\w+)/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexWithOnlyEndAnchor(): void
    {
        $compiled = Router::compilePattern('/test/{(\w+)/baz$}');

        // Deve remover apenas $
        $this->assertEquals('#^/test/(\w+)/baz/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexWithoutAnchors(): void
    {
        $compiled = Router::compilePattern('/test/{(\d{4})-(\d{2})-(\d{2})}');

        // Não deve alterar nada
        $this->assertEquals('#^/test/(\d{4})-(\d{2})-(\d{2})/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexWithAnchorsInMiddle(): void
    {
        // Âncoras no meio do pattern devem ser preservadas
        $compiled = Router::compilePattern('/test/{(start|^middle$|end)}');

        $this->assertEquals('#^/test/(start|^middle$|end)/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testComplexRegexWithNestedGroups(): void
    {
        // Usar um padrão que não cause conflito com o processamento de parâmetros
        $compiled = Router::compilePattern('/files/{^([a-z]+_[a-z]+)/(\d{4})\.([a-z]{3,4})$}');

        // Deve remover âncoras externas mas preservar a estrutura interna (ponto será escapado)
        $this->assertEquals('#^/files/([a-z]+_[a-z]+)/(\d{4})\\\\.([a-z]{3,4})/?$#', $compiled['pattern']);
    }

    /**
     * @test
     */
    public function testRegexMatchingWithRemovedAnchors(): void
    {
        $pattern = '/archive/{^(\d{4})/(\d{2})/(.+)$}';
        $compiled = Router::compilePattern($pattern);

        // Testa que o pattern compilado funciona corretamente
        $this->assertMatchesRegularExpression($compiled['pattern'], '/archive/2025/07/my-post');
        $this->assertMatchesRegularExpression($compiled['pattern'], '/archive/2025/07/my-post/');

        // Extrai os matches
        preg_match($compiled['pattern'], '/archive/2025/07/my-post', $matches);
        $this->assertEquals('2025', $matches[1]);
        $this->assertEquals('07', $matches[2]);
        $this->assertEquals('my-post', $matches[3]);
    }

    /**
     * @test
     */
    public function testMultipleRegexBlocks(): void
    {
        $compiled = Router::compilePattern('/api/{^v(\d+)$}/users/{^(\d+)$}');

        // Ambos blocos devem ter âncoras removidas
        $this->assertEquals('#^/api/v(\d+)/users/(\d+)/?$#', $compiled['pattern']);
    }
}
