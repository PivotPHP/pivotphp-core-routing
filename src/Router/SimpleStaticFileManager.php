<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Simple Static File Manager
 *
 * Implementação simples e direta para servir arquivos estáticos.
 *
 * ESTRATÉGIA: Registra cada arquivo como uma rota individual.
 * - Sem complexidade de wildcards ou regex
 * - Cada arquivo físico = uma rota específica no router
 * - Performance alta para poucos arquivos
 * - Memória proporcional ao número de arquivos
 *
 * USO RECOMENDADO:
 * - Projetos pequenos com <100 arquivos estáticos
 * - Quando você quer controle total sobre quais arquivos são servidos
 * - Quando performance de roteamento é crítica
 *
 * @package PivotPHP\Routing\Router
 * @since 1.1.3
 */
class SimpleStaticFileManager
{
    /**
     * Arquivos registrados
     * @var array<string, array{path: string, mime: string, size: int}>
     */
    private static array $registeredFiles = [];

    /**
     * Estatísticas
     * @var array<string, int>
     */
    private static array $stats = [
        'registered_files' => 0,
        'total_hits' => 0,
        'memory_usage_bytes' => 0
    ];

    /**
     * Configurações
     * @var array<string, mixed>
     */
    private static array $config = [
        'max_file_size' => 10485760,    // 10MB
        'allowed_extensions' => [
            'js', 'css', 'html', 'htm', 'json', 'xml',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
            'woff', 'woff2', 'ttf', 'eot',
            'pdf', 'txt', 'md'
        ],
        'cache_control_max_age' => 86400 // 24 horas
    ];

    /**
     * MIME types
     */
    private const MIME_TYPES = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'htm' => 'text/html',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/markdown'
    ];

    /**
     * Registra um diretório inteiro, criando rotas para cada arquivo
     * @param array<string, mixed> $options
     */
    public static function registerDirectory(
        string $routePrefix,
        string $physicalPath,
        array $options = []
    ): void {
        // Suprime warning sobre $options não usado - reservado para funcionalidades futuras
        unset($options);
        if (!is_dir($physicalPath)) {
            throw new \InvalidArgumentException("Directory does not exist: {$physicalPath}");
        }

        $routePrefix = '/' . trim($routePrefix, '/');
        $physicalPath = rtrim($physicalPath, '/\\');

        // Escaneia todos os arquivos no diretório
        $files = self::scanDirectory($physicalPath);

        foreach ($files as $file) {
            // Constrói rota baseada no caminho relativo
            $filePath = $file['path'];
            $relativePath = str_replace($physicalPath, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);
            $route = $routePrefix . $relativePath;

            // Registra rota individual para este arquivo
            self::registerSingleFile($route, $file);
        }
    }

    /**
     * Registra um único arquivo como rota estática
     * @param array{path: string, size: int, mime: string, extension: string} $fileInfo
     */
    private static function registerSingleFile(string $route, array $fileInfo): void
    {
        // Cria handler específico para este arquivo
        $handler = self::createFileHandler($fileInfo);

        // Registra no router
        Router::add('GET', $route, $handler);

        // Armazena informações
        self::$registeredFiles[$route] = [
            'path' => $fileInfo['path'],
            'mime' => $fileInfo['mime'],
            'size' => $fileInfo['size']
        ];

        self::$stats['registered_files']++;
        self::$stats['memory_usage_bytes'] += $fileInfo['size'];
    }

    /**
     * Cria handler para um arquivo específico
     * @param array{path: string, size: int, mime: string, extension: string} $fileInfo
     * @return callable(ServerRequestInterface, ResponseInterface): ResponseInterface
     */
    private static function createFileHandler(array $fileInfo): callable
    {
        return static function ($req, $res) use ($fileInfo) {
            $response = self::toPsr7($res);
            self::$stats['total_hits']++;

            // Lê conteúdo do arquivo
            $content = file_get_contents($fileInfo['path']);
            if ($content === false) {
                throw new \RuntimeException('Cannot read file: ' . $fileInfo['path']);
            }

            // Headers de resposta
            $response = $response->withHeader('Content-Type', $fileInfo['mime'])
                      ->withHeader('Content-Length', (string) strlen($content));

            // Headers de cache
            $cacheMaxAge = self::$config['cache_control_max_age'];
            if (is_numeric($cacheMaxAge) && $cacheMaxAge > 0) {
                $maxAge = (int) $cacheMaxAge;
                $response = $response->withHeader('Cache-Control', "public, max-age={$maxAge}");
            }

            // ETag baseado no arquivo
            $filemtime = filemtime($fileInfo['path']);
            $etag = md5($fileInfo['path'] . ($filemtime !== false ? (string) $filemtime : '0') . $fileInfo['size']);
            $response = $response->withHeader('ETag', '"' . $etag . '"');

            // Last-Modified
            $lastModified = gmdate('D, d M Y H:i:s', $filemtime !== false ? $filemtime : 0) . ' GMT';
            $response = $response->withHeader('Last-Modified', $lastModified);

            // Define o body no stream do response PSR-7 (mutável) e retorna
            $response->getBody()->write($content);
            return $response;
        };
    }

    /**
     * Normaliza para uma resposta PSR-7 (aceita PSR-7 ou uma fachada com psr7()).
     */
    private static function toPsr7(mixed $res): ResponseInterface
    {
        if ($res instanceof ResponseInterface) {
            return $res;
        }

        if (is_object($res) && method_exists($res, 'psr7')) {
            $inner = $res->psr7();
            if ($inner instanceof ResponseInterface) {
                return $inner;
            }
        }

        throw new \InvalidArgumentException('Static file handler expects a PSR-7 response or a facade with psr7().');
    }

    /**
     * Escaneia diretório recursivamente
     * @return array<int, array{path: string, size: int, mime: string, extension: string}>
     */
    private static function scanDirectory(string $path): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $extension = strtolower($file->getExtension());

                // Verifica se extensão é permitida
                $allowedExtensions = self::$config['allowed_extensions'];
                if (!is_array($allowedExtensions) || !in_array($extension, $allowedExtensions, true)) {
                    continue;
                }

                // Verifica tamanho
                $maxFileSizeConfig = self::$config['max_file_size'];
                $maxFileSize = is_numeric($maxFileSizeConfig) ? (int)$maxFileSizeConfig : 10485760;
                if ($file->getSize() > $maxFileSize) {
                    continue;
                }

                $files[] = [
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
                    'extension' => $extension
                ];
            }
        }

        return $files;
    }

    /**
     * Configura o manager
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Obtém estatísticas
     * @return array<string, int>
     */
    public static function getStats(): array
    {
        return self::$stats;
    }

    /**
     * Lista arquivos registrados
     * @return array<int, string>
     */
    public static function getRegisteredFiles(): array
    {
        return array_keys(self::$registeredFiles);
    }

    /**
     * Limpa cache
     */
    public static function clearCache(): void
    {
        self::$registeredFiles = [];
        self::$stats = [
            'registered_files' => 0,
            'total_hits' => 0,
            'memory_usage_bytes' => 0
        ];
    }
}
