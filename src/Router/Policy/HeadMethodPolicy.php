<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Policy;

use PivotPHP\Routing\Router\Resolution\MethodNotAllowed;
use PivotPHP\Routing\Router\Resolution\RouteNotFound;
use PivotPHP\Routing\Router\Resolution\RouteResolution;
use PivotPHP\Routing\Router\RouteIndex;

/**
 * Política para o método HEAD (SPEC-072).
 * Prefere rota HEAD explícita; caso contrário, delega para GET.
 */
final class HeadMethodPolicy implements MethodPolicy
{
    public function resolve(
        string $method,
        string $normalizedPath,
        string $rawPath,
        RouteIndex $index
    ): RouteResolution {
        // 1. Tenta correspondência com rota HEAD explícita
        $matched = $index->match('HEAD', $normalizedPath, $rawPath);
        if ($matched !== null) {
            return $matched;
        }

        // 2. Fallback de HEAD para rota GET
        $getMatched = $index->match('GET', $normalizedPath, $rawPath);
        if ($getMatched !== null) {
            return $getMatched;
        }

        // 3. Verifica outros métodos permitidos
        $allowed = $index->getAllowedMethodsForPath($normalizedPath, $rawPath);
        if ($allowed !== []) {
            return new MethodNotAllowed($allowed);
        }

        return new RouteNotFound();
    }
}
