<?php

namespace PivotPHP\Routing\Utils;

/**
 * Classe Utils com utilitários gerais para o framework.
 */
class Utils
{
    /**
     * Constante para suprimir saída de log.
     */
    public const DEST_SUPPRESS = '__SUPPRESS__';

    /**
     * Sanitização universal de string.
     *
     * @param  mixed $value
     * @return mixed
     */
    public static function sanitizeString($value)
    {
        return is_string($value) ? trim(strip_tags($value)) : $value;
    }

    /**
     * Formata bytes em formato legível para humanos.
     *
     * @param  int|float $bytes
     * @param  int $precision
     * @return string
     */
    public static function formatBytes($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Sanitização de email.
     *
     * @param  mixed $value
     * @return mixed
     */
    public static function sanitizeEmail($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Sanitização de array.
     *
     * @param  mixed $arr
     * @return mixed
     */
    public static function sanitizeArray($arr)
    {
        return is_array($arr) ? array_map([self::class, 'sanitizeString'], $arr) : $arr;
    }

    /**
     * Validação de email.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function isEmail($value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validação de boolean.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function isBool($value): bool
    {
        return is_bool($value) || $value === '0' || $value === '1' || $value === 0 || $value === 1;
    }

    /**
     * Validação de inteiro.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function isInt($value): bool
    {
        return is_numeric($value) && (float) $value === (float) (int) $value;
    }

    /**
     * Verificar se um valor é estritamente uma string.
     *
     * Este método retorna true apenas para valores que são do tipo string.
     * Valores numéricos (int, float) retornam false.
     *
     * @param  mixed $value O valor a ser verificado
     * @return bool True se for string, false caso contrário
     *
     * @example
     * Utils::isString('hello');  // true
     * Utils::isString('123');    // true
     * Utils::isString(123);      // false
     * Utils::isString(12.34);    // false
     */
    public static function isString($value): bool
    {
        return is_string($value);
    }

    /**
     * Verificar se um valor é uma string ou numérico (conversível para string).
     *
     * Este método retorna true para strings e valores numéricos que podem
     * ser convertidos para string de forma segura.
     *
     * @param  mixed $value O valor a ser verificado
     * @return bool True se for string ou numérico, false caso contrário
     *
     * @example
     * Utils::isStringOrNumeric('hello');  // true
     * Utils::isStringOrNumeric('123');    // true
     * Utils::isStringOrNumeric(123);      // true
     * Utils::isStringOrNumeric(12.34);    // true
     * Utils::isStringOrNumeric([]);       // false
     */
    public static function isStringOrNumeric($value): bool
    {
        return is_string($value) || is_numeric($value);
    }

    /**
     * Validação de array.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function isArray($value): bool
    {
        return is_array($value);
    }

    /**
     * Helper para cabeçalhos CORS.
     *
     * @param  array<string> $origins
     * @param  array<string> $methods
     * @param  array<string> $headers
     * @return array<string, string>
     */
    public static function corsHeaders(
        array $origins = ['*'],
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): array {
        return [
            'Access-Control-Allow-Origin' => implode(',', $origins),
            'Access-Control-Allow-Methods' => implode(',', $methods),
            'Access-Control-Allow-Headers' => implode(',', $headers),
            'Access-Control-Allow-Credentials' => 'true',
        ];
    }

    /**
     * Logger simples.
     *
     * @param  string $msg
     * @param  string $level
     * @param  string|null $destination Pode ser 'null' para suprimir, 'stdout', 'stderr' ou caminho de arquivo
     * @return void
     */
    public static function log(
        string $msg,
        string $level = 'info',
        ?string $destination = null
    ): void {
        if ($destination === self::DEST_SUPPRESS) {
            // Não faz nada, suprime saída
            return;
        }
        $date = date('Y-m-d H:i:s');
        $formatted = "[$date][$level] $msg";
        if ($destination === null) {
            error_log($formatted);
        } elseif ($destination === 'stdout') {
            file_put_contents('php://stdout', $formatted . "\n");
        } elseif ($destination === 'stderr') {
            file_put_contents('php://stderr', $formatted . "\n");
        } else {
            file_put_contents($destination, $formatted . "\n", FILE_APPEND);
        }
    }

    /**
     * Geração de token seguro.
     *
     * @param  int $length
     * @return string
     */
    public static function randomToken(int $length = 32): string
    {
        $bytesLength = max(1, (int)($length / 2));
        return bin2hex(random_bytes($bytesLength));
    }

    /**
     * Gera um token CSRF.
     *
     * @return string
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $token = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || $token === '') {
            $token = self::randomToken(32);
            $_SESSION['csrf_token'] = $token;
        }
        return $token;
    }

    /**
     * Verifica um token CSRF.
     *
     * @param  string $token
     * @return bool
     */
    public static function checkCsrf(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $stored = $_SESSION['csrf_token'] ?? null;
        return is_string($stored) && hash_equals($stored, $token);
    }

    /**
     * Converte string para camelCase.
     *
     * @param  string $string
     * @return string
     */
    public static function camelCase(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string))));
    }

    /**
     * Converte string para snake_case.
     *
     * @param  string $string
     * @return string
     */
    public static function snakeCase(string $string): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $string);
        return strtolower($result ?? $string);
    }

    /**
     * Converte string para kebab-case.
     *
     * @param  string $string
     * @return string
     */
    public static function kebabCase(string $string): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '-$0', $string);
        return strtolower($result ?? $string);
    }

    /**
     * Gera um UUID v4.
     *
     * @return string
     */
    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Verifica se uma string é JSON válido.
     *
     * @param  string $string
     * @return bool
     */
    public static function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Trunca uma string com reticências.
     *
     * @param  string $string
     * @param  int    $length
     * @param  string $suffix
     * @return string
     */
    public static function truncate(
        string $string,
        int $length,
        string $suffix = '...'
    ): string {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - strlen($suffix)) . $suffix;
    }

    /**
     * Gera uma slug para URL.
     *
     * @param  string $string
     * @return string
     */
    public static function slug(string $string): string
    {
        $string = preg_replace('/[^\p{L}\d\s-]/u', '', $string) ?? $string;
        $string = preg_replace('/[\s-]+/', '-', trim($string)) ?? $string;
        return strtolower($string);
    }
}
