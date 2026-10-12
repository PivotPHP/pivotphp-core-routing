<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Policy;

use PivotPHP\Routing\Router\Resolution\RouteResolution;
use PivotPHP\Routing\Router\RouteIndex;

/**
 * Estratégia de resolução por método HTTP (Padrão Strategy).
 */
interface MethodPolicy
{
    /**
     * Resolve a rota contra o índice fornecido de acordo com as regras semânticas do método HTTP.
     */
    public function resolve(
        string $method,
        string $normalizedPath,
        string $rawPath,
        RouteIndex $index
    ): RouteResolution;
}
