<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

/**
 * Controller de instância sem dependências externas (no-arg constructor),
 * usado para testar a resolução lazy de [Classe::class, 'métodoDeInstância'].
 */
final class InstanceController
{
    public function index(...$args): string
    {
        return 'index called with ' . count($args) . ' args';
    }

    public static function staticMethod(): string
    {
        return 'static';
    }
}
