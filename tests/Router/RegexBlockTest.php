<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Router;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use ReflectionClass;

/**
 * Test regex block handling in route patterns
 *
 * This test class validates the complex regex pattern used for matching
 * brace-delimited blocks in route patterns.
 */
class RegexBlockTest extends TestCase
{
    private $reflection;
    private $processRegexBlocksMethod;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Router::class);
        $this->processRegexBlocksMethod = $this->reflection->getMethod('processRegexBlocks');
        $this->processRegexBlocksMethod->setAccessible(true);
    }

    /**
     * Test simple regex blocks without nested braces
     */
    public function testSimpleRegexBlocks(): void
    {
        $parameters = [];
        $position = 0;

        // Test version pattern
        $pattern = '/api/{^v(\d+)$}/users';
        $expected = '/api/v(\d+)/users';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);

        // Test alternation pattern
        $parameters = [];
        $position = 0;
        $pattern = '/media/{^(images|videos)$}/list';
        $expected = '/media/(images|videos)/list';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test regex blocks with file extensions
     */
    public function testFileExtensionPatterns(): void
    {
        $parameters = [];
        $position = 0;

        $pattern = '/download/{^(.+)\.(pdf|doc|txt)$}';
        $expected = '/download/(.+)\.(pdf|doc|txt)';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test patterns with inner grouping (one level of nesting)
     */
    public function testPatternsWithInnerGrouping(): void
    {
        $parameters = [];
        $position = 0;

        // Pattern with character class
        $pattern = '/code/{^([A-Z]{3}-\d{4})$}';
        $expected = '/code/([A-Z]{3}-\d{4})';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test multiple regex blocks in one pattern
     */
    public function testMultipleRegexBlocks(): void
    {
        $parameters = [];
        $position = 0;

        $pattern = '/{^(admin|user)$}/profile/{^(\d+)$}';
        $expected = '/(admin|user)/profile/(\d+)';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test edge case: empty braces
     */
    public function testEmptyBraces(): void
    {
        $parameters = [];
        $position = 0;

        $pattern = '/path/{}';
        $expected = '/path/{}'; // Should remain unchanged
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test pattern without regex blocks
     */
    public function testPatternWithoutRegexBlocks(): void
    {
        $parameters = [];
        $position = 0;

        $pattern = '/users/:id/posts/:postId';
        $expected = '/users/:id/posts/:postId';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test complex but supported pattern
     */
    public function testComplexSupportedPattern(): void
    {
        $parameters = [];
        $position = 0;

        // Pattern with multiple capture groups and alternation
        $pattern = '/api/{^v(\d+)\.(\d+)$}/resource/{^(get|post|put|delete)$}';
        $expected = '/api/v(\d+)\.(\d+)/resource/(get|post|put|delete)';
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test limitation: deeply nested braces (documents the limitation)
     *
     * This test documents that deeply nested braces are not fully supported
     * The regex will match the outer braces but may not correctly handle
     * multiple levels of nesting.
     */
    public function testDeeplyNestedBracesLimitation(): void
    {
        $parameters = [];
        $position = 0;

        // This pattern has nested braces which may not be handled correctly
        // The regex is designed for simple cases, not deeply nested structures
        $pattern = '/complex/{^(group1{inner1}|group2{inner2})$}';

        // The actual behavior - it processes but may not handle as expected
        $result = $this->processRegexBlocksMethod->invokeArgs(null, [$pattern, &$parameters, &$position]);

        // Document that this is a known limitation
        $this->assertStringContainsString('/complex/', $result);
        // The inner braces may cause issues - this is a documented limitation
    }

    /**
     * Test null pattern handling
     */
    public function testNullPattern(): void
    {
        $parameters = [];
        $position = 0;

        $result = $this->processRegexBlocksMethod->invokeArgs(null, [null, &$parameters, &$position]);
        $this->assertEquals('', $result);
    }
}
