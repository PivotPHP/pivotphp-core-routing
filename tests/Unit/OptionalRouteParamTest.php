<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;

/**
 * Cobre que parâmetros opcionais (:param?) são rejeitados com erro claro (SPEC-026).
 */
class OptionalRouteParamTest extends TestCase
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
    public function testOptionalParameterIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional route parameters are not supported');

        Router::get('/opt/:id?', function () {
            return 1;
        });
    }
}
