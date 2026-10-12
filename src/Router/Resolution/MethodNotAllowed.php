<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router\Resolution;

/**
 * Resultado de rota existente para o caminho, mas com método HTTP não permitido (405 / OPTIONS).
 */
final class MethodNotAllowed extends RouteResolution
{
    /**
     * @param array<int, string> $allowedMethods Métodos HTTP permitidos para o caminho
     */
    public function __construct(
        private readonly array $allowedMethods
    ) {
    }

    public function isMatched(): bool
    {
        return false;
    }

    public function isMethodNotAllowed(): bool
    {
        return true;
    }

    public function isNotFound(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * Formata os métodos permitidos como string para o header Allow (ex: "GET, POST, HEAD").
     */
    public function getAllowHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }
}
