<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Cobre que os caches por URL (exactMatchCache/prefixMatchCache) são limitados
 * (SPEC-046 — crescimento sem limite em workers persistentes).
 */
class RouterCacheBoundedTest extends TestCase
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
    public function testExactMatchCacheIsBounded(): void
    {
        Router::get('/users/:id', function () {
            return 1;
        });

        for ($i = 0; $i < 1500; $i++) {
            Router::identify('GET', '/users/' . $i);
        }

        $cache = $this->readStatic('exactMatchCache');
        $this->assertLessThanOrEqual(1000, count($cache));
    }

    /**
     * @test
     */
    public function testPrefixMatchCacheIsBounded(): void
    {
        Router::group('/api', function () {
            Router::get('/:id', function () {
                return 1;
            });
        });

        for ($i = 0; $i < 1500; $i++) {
            Router::identify('GET', '/api/' . $i);
        }

        $cache = $this->readStatic('prefixMatchCache');
        $this->assertLessThanOrEqual(1000, count($cache));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStatic(string $property): array
    {
        $reflection = new \ReflectionClass(Router::class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $value = $prop->getValue();

        return is_array($value) ? $value : [];
    }
}
