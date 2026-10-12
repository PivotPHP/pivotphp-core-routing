<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Resolution;

/**
 * Representa o resultado tipado da resolução de uma rota (Padrão Facade / Result Object).
 */
abstract class RouteResolution
{
    abstract public function isMatched(): bool;

    abstract public function isMethodNotAllowed(): bool;

    abstract public function isNotFound(): bool;
}
