<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Policy;

use PivotPHP\Routing\Router\Resolution\MethodNotAllowed;
use PivotPHP\Routing\Router\Resolution\RouteNotFound;
use PivotPHP\Routing\Router\Resolution\RouteResolution;
use PivotPHP\Routing\Router\RouteIndex;

/**
 * Política para o método OPTIONS (SPEC-072 / SPEC-114).
 * Se houver rota OPTIONS explícita, retorna RouteMatched para executar o handler.
 * Caso contrário, se o caminho existir para outros métodos, retorna MethodNotAllowed com os métodos permitidos.
 */
final class OptionsMethodPolicy implements MethodPolicy
{
    public function resolve(
        string $method,
        string $normalizedPath,
        string $rawPath,
        RouteIndex $index
    ): RouteResolution {
        // 1. Tenta rota OPTIONS explícita
        $matched = $index->match('OPTIONS', $normalizedPath, $rawPath);
        if ($matched !== null) {
            return $matched;
        }

        // 2. Sem OPTIONS explícito, verifica se existem outros métodos registrados para o caminho
        $allowed = $index->getAllowedMethodsForPath($normalizedPath, $rawPath);
        if ($allowed !== []) {
            return new MethodNotAllowed($allowed);
        }

        // 3. Caminho inexistente
        return new RouteNotFound();
    }
}
