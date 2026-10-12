<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Resolution;

/**
 * Resultado de rota inexistente para o caminho solicitado (404).
 */
final class RouteNotFound extends RouteResolution
{
    public function isMatched(): bool
    {
        return false;
    }

    public function isMethodNotAllowed(): bool
    {
        return false;
    }

    public function isNotFound(): bool
    {
        return true;
    }
}
