<?php

namespace PivotPHP\Routing\Utils;

/**
 * Utilitários para manipulação de arrays.
 *
 * Fornece métodos helper para trabalhar com arrays,
 * incluindo suporte a dot notation para acesso aninhado.
 */
class Arr
{
    /**
     * Obtém um valor de um array usando dot notation.
     *
     * @param  array<mixed> $array   Array de origem
     * @param  string       $key     Chave em dot notation (ex: 'user.profile.name')
     * @param  mixed        $default Valor padrão se não
     *                               encontrado
     * @return mixed
     */
    public static function get(
        array $array,
        string $key,
        $default = null
    ) {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Define um valor em um array usando dot notation.
     *
     * @param  array<mixed> $array Array de destino (passado por referência)
     * @param  string       $key   Chave em dot notation
     * @param  mixed        $value Valor a ser definido
     * @return void
     */
    public static function set(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $keyPart) {
            if ($i === count($keys) - 1) {
                $current[$keyPart] = $value;
            } else {
                if (!isset($current[$keyPart]) || !is_array($current[$keyPart])) {
                    $current[$keyPart] = [];
                }
                $current = &$current[$keyPart];
            }
        }
    }

    /**
     * Verifica se uma chave existe em um array usando dot notation.
     *
     * @param  array<mixed> $array Array a ser verificado
     * @param  string       $key   Chave em dot notation
     * @return bool
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Remove uma chave de um array usando dot notation.
     *
     * @param  array<mixed> $array Array de origem (passado por referência)
     * @param  string       $key   Chave em dot notation
     * @return void
     */
    public static function forget(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $key = $keys[$i];
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return;
            }
            $current = &$current[$key];
        }

        unset($current[end($keys)]);
    }

    /**
     * Achata um array multidimensional usando dot notation.
     *
     * @param  array<mixed> $array   Array a ser achatado
     * @param  string       $prepend Prefixo para as chaves
     * @return array<string, mixed>
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $newKey = ($prepend !== '') ? $prepend . '.' . $key : $key;

            if (is_array($value) && count($value) > 0) {
                $results = array_merge($results, static::dot($value, $newKey));
            } else {
                $results[$newKey] = $value;
            }
        }

        return $results;
    }

    /**
     * Converte um array achatado de volta para multidimensional.
     *
     * @param  array<string, mixed> $array Array achatado
     * @return array<mixed>
     */
    public static function undot(array $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            static::set($results, $key, $value);
        }

        return $results;
    }

    /**
     * Filtra um array mantendo apenas as chaves especificadas.
     *
     * @param  array<mixed>         $array Array de origem
     * @param  array<string>|string $keys  Chaves a serem mantidas
     * @return array<mixed>
     */
    public static function only(array $array, $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args()[1];

        // Garantir que $keys seja array de strings/ints para array_flip
        if (!is_array($keys)) {
            return [];
        }

        $validKeys = [];
        foreach ($keys as $key) {
            if (is_string($key) || is_int($key)) {
                $validKeys[] = $key;
            }
        }

        return array_intersect_key($array, array_flip($validKeys));
    }

    /**
     * Filtra um array removendo as chaves especificadas.
     *
     * @param  array<mixed>         $array Array de origem
     * @param  array<string>|string $keys  Chaves a serem removidas
     * @return array<mixed>
     */
    public static function except(array $array, $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args()[1];

        // Garantir que $keys seja array de strings/ints para array_flip
        if (!is_array($keys)) {
            return $array;
        }

        $validKeys = [];
        foreach ($keys as $key) {
            if (is_string($key) || is_int($key)) {
                $validKeys[] = $key;
            }
        }

        return array_diff_key($array, array_flip($validKeys));
    }

    /**
     * Obtém o primeiro elemento de um array.
     *
     * @param  array<mixed> $array   Array de origem
     * @param  mixed        $default Valor padrão se array estiver
     *                               vazio
     * @return mixed
     */
    public static function first(array $array, $default = null)
    {
        if (count($array) === 0) {
            return $default;
        }

        return reset($array);
    }

    /**
     * Obtém o último elemento de um array.
     *
     * @param  array<mixed> $array   Array de origem
     * @param  mixed        $default Valor padrão se array estiver
     *                               vazio
     * @return mixed
     */
    public static function last(array $array, $default = null)
    {
        if (count($array) === 0) {
            return $default;
        }

        return end($array);
    }

    /**
     * Verifica se um array é associativo.
     *
     * @param  array<mixed> $array Array a ser verificado
     * @return bool
     */
    public static function isAssoc(array $array): bool
    {
        if (count($array) === 0) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Merge recursivo de arrays com preservação de índices numéricos.
     *
     * @param  array<mixed> $array1 Primeiro array
     * @param  array<mixed> $array2 Segundo array
     * @return array<mixed>
     */
    public static function mergeRecursive(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = static::mergeRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Mapeia um array mantendo as chaves.
     *
     * @param  array<mixed> $array    Array a ser mapeado
     * @param  callable     $callback Função de
     *                                callback
     * @return array<mixed>
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $result;
    }

    /**
     * Agrupa elementos de um array por uma chave.
     *
     * @param  array<mixed>    $array   Array a ser agrupado
     * @param  string|callable $groupBy Chave ou função para agrupamento
     * @return array<mixed>
     */
    public static function groupBy(array $array, $groupBy): array
    {
        $results = [];

        foreach ($array as $item) {
            if (is_callable($groupBy)) {
                $key = $groupBy($item);
            } elseif (is_array($item)) {
                $key = static::get($item, $groupBy);
            } else {
                $key = 'unknown';
            }
            if (!is_int($key) && !is_string($key)) {
                $key = is_scalar($key) ? (string) $key : 'unknown';
            }
            $results[$key][] = $item;
        }

        return $results;
    }

    /**
     * Achata um array multidimensional em um único nível.
     *
     * @param  array<mixed> $array Array a ser achatado
     * @param  int          $depth Profundidade máxima (0 =
     *                             ilimitado)
     * @return array<mixed>
     */
    public static function flatten(array $array, int $depth = 0): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ($depth > 1 || $depth === 0)) {
                $flattened = static::flatten($value, $depth === 0 ? 0 : $depth - 1);
                foreach ($flattened as $subKey => $subValue) {
                    $result[$key . '.' . $subKey] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Divide um array em chunks de tamanho específico.
     *
     * @param  array<mixed> $array        Array a ser dividido
     * @param  int          $size         Tamanho de cada chunk
     * @param  bool         $preserveKeys Se deve preservar as chaves
     * @return array<array<mixed>>
     */
    public static function chunk(
        array $array,
        int $size,
        bool $preserveKeys = true
    ): array {
        if ($size <= 0) {
            return [];
        }

        return array_chunk($array, $size, $preserveKeys);
    }

    /**
     * Embaralha um array preservando as chaves.
     *
     * @param  array<mixed> $array Array de origem
     * @return array<mixed>
     */
    public static function shuffle(array $array): array
    {
        $keys = array_keys($array);
        shuffle($keys);

        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $array[$key];
        }

        return $shuffled;
    }
}
