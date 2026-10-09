<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Utils;

use InvalidArgumentException;

/**
 * CallableResolver - Utilitário para resolver diferentes tipos de callables
 *
 * Suporta:
 * - Closures/Anonymous functions
 * - Array callables [$object, 'method'] e [Class::class, 'method']
 * - String functions
 * - String method notation (opcional)
 */
class CallableResolver
{
    /**
     * Resolve um handler para um callable válido
     *
     * @param mixed $handler O handler a ser resolvido
     * @return callable O callable resolvido
     * @throws InvalidArgumentException Se o handler não for válido
     */
    public static function resolve(mixed $handler): callable
    {
        // Verificação direta para closures e funções válidas
        if (is_callable($handler)) {
            return $handler;
        }

        // Resolução específica para array callables
        if (is_array($handler) && count($handler) === 2) {
            return self::resolveArrayCallable($handler);
        }

        // Resolução para strings
        if (is_string($handler)) {
            return self::resolveStringCallable($handler);
        }

        throw new InvalidArgumentException(
            sprintf(
                'Handler must be a callable. Given: %s',
                self::getTypeDescription($handler)
            )
        );
    }

    /**
     * Resolve array callables [$object, 'method'] ou [Class::class, 'method']
     *
     * @param array<int, mixed> $handler Array com [objeto/classe, método]
     * @return callable
     * @throws InvalidArgumentException
     */
    private static function resolveArrayCallable(array $handler): callable
    {
        [$objectOrClass, $method] = $handler;

        // Validar que o método é uma string
        if (!is_string($method)) {
            throw new InvalidArgumentException(
                'Array callable second element must be a string method name'
            );
        }

        // Caso 1: Método estático [Class::class, 'method']
        if (is_string($objectOrClass)) {
            if (!class_exists($objectOrClass)) {
                throw new InvalidArgumentException(
                    "Class '{$objectOrClass}' does not exist"
                );
            }

            if (!method_exists($objectOrClass, $method)) {
                throw new InvalidArgumentException(
                    "Route handler validation failed: Static method '{$objectOrClass}::{$method}' does not exist"
                );
            }

            // Verificar se o método é estático ou de instância
            $reflection = new \ReflectionMethod($objectOrClass, $method);

            if ($reflection->isStatic()) {
                /** @var callable */
                return [$objectOrClass, $method];
            }

            // Método de instância referenciado pelo nome da classe: resolve de
            // forma lazy (instancia na chamada). Este pacote é standalone e não
            // possui container de DI; consumidores com DI podem resolver e passar
            // a instância diretamente ([$instance, 'method']).
            return static function (...$args) use ($objectOrClass, $method) {
                $instance = new $objectOrClass();

                /** @var callable $handler */
                $handler = [$instance, $method];

                return $handler(...$args);
            };
        }

        // Caso 2: Método de instância [$instance, 'method']
        if (is_object($objectOrClass)) {
            $className = get_class($objectOrClass);

            if (!method_exists($objectOrClass, $method)) {
                throw new InvalidArgumentException(
                    "Route handler validation failed: Method '{$className}::{$method}' does not exist"
                );
            }

            // Verificar se o método é acessível
            $reflection = new \ReflectionMethod($objectOrClass, $method);
            if (!$reflection->isPublic()) {
                throw new InvalidArgumentException(
                    "Method '{$className}::{$method}' is not public"
                );
            }

            /** @var callable */
            return [$objectOrClass, $method];
        }

        throw new InvalidArgumentException(
            'Array callable first element must be a class name string or object instance'
        );
    }

    /**
     * Resolve string callables
     *
     * @param string $handler String function name
     * @return callable
     * @throws InvalidArgumentException
     */
    private static function resolveStringCallable(string $handler): callable
    {
        // Verificar se é uma função global
        if (function_exists($handler)) {
            return $handler;
        }

        // Futuro: Suporte para notação Controller@method se necessário
        // if (strpos($handler, '@') !== false) {
        //     return self::resolveControllerMethod($handler);
        // }

        throw new InvalidArgumentException(
            "Function '{$handler}' does not exist"
        );
    }

    /**
     * Verifica se um valor é um callable válido
     *
     * @param mixed $value
     * @return bool
     */
    public static function isCallable(mixed $value): bool
    {
        try {
            self::resolve($value);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Obtém uma descrição do tipo para mensagens de erro
     *
     * @param mixed $value
     * @return string
     */
    private static function getTypeDescription(mixed $value): string
    {
        if (is_object($value)) {
            return 'object(' . get_class($value) . ')';
        }

        if (is_array($value)) {
            return 'array(' . count($value) . ' elements)';
        }

        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }

        return gettype($value);
    }

    /**
     * Valida e executa um callable com argumentos
     *
     * @param mixed $handler O handler a ser executado
     * @param mixed ...$args Argumentos para o callable
     * @return mixed O resultado da execução
     * @throws InvalidArgumentException Se o handler não for válido
     */
    public static function call(mixed $handler, mixed ...$args): mixed
    {
        $callable = self::resolve($handler);
        return $callable(...$args);
    }
}
