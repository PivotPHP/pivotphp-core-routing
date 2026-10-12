<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Policy;

use PivotPHP\Routing\Router\Resolution\MethodNotAllowed;
use PivotPHP\Routing\Router\Resolution\RouteNotFound;
use PivotPHP\Routing\Router\Resolution\RouteResolution;
use PivotPHP\Routing\Router\RouteIndex;

/**
 * Política padrão para métodos HTTP diretos (GET, POST, PUT, DELETE, PATCH).
 */
final class DefaultMethodPolicy implements MethodPolicy
{
    public function resolve(
        string $method,
        string $normalizedPath,
        string $rawPath,
        RouteIndex $index
    ): RouteResolution {
        // 1. Tenta correspondência direta
        $matched = $index->match($method, $normalizedPath, $rawPath);
        if ($matched !== null) {
            return $matched;
        }

        // 2. Se não casou, verifica se o caminho existe para outros métodos (405)
        $allowed = $index->getAllowedMethodsForPath($normalizedPath, $rawPath);
        if ($allowed !== []) {
            return new MethodNotAllowed($allowed);
        }

        // 3. Caminho inexistente (404)
        return new RouteNotFound();
    }
}
