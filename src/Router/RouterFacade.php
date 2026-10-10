<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

/**
 * Fachada estática legada para o Router.
 *
 * Delega chamadas para uma instância padrão de Router.
 *
 * @deprecated Use instâncias de `Router` diretamente.
 */
class RouterFacade
{
    private static ?Router $instance = null;

    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new Router();
        }

        return self::$instance;
    }

    public static function setInstance(?Router $instance): void
    {
        self::$instance = $instance;
    }

    public static function clear(): void
    {
        self::getInstance()->clear();
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = self::getInstance();

        /** @phpstan-ignore-next-line Dynamic method dispatch on Router. */
        return $instance->{$method}(...$args);
    }
}

