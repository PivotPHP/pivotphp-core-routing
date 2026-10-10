<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Utils\CallableResolver;
use PivotPHP\Routing\Utils\Utils;

/**
 * Regression tests for the type fixes required by PHPStan 2 (SPEC-057).
 */
final class UtilsTypeSafetyTest extends TestCase
{
    public function testAssociativeTwoElementArrayIsNotTreatedAsArrayCallable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CallableResolver::resolve(['class' => \stdClass::class, 'method' => 'x']);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function intValues(): iterable
    {
        yield 'int' => [5, true];
        yield 'int string' => ['5', true];
        yield 'float with zero fraction' => ['5.0', true];
        yield 'negative' => ['-3', true];
        yield 'fraction' => ['5.5', false];
        yield 'non numeric' => ['abc', false];
        yield 'null' => [null, false];
    }

    #[DataProvider('intValues')]
    public function testIsInt(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Utils::isInt($value));
    }
}
