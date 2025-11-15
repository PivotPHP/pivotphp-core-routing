<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use PivotPHP\Core\Core\Application;
use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Http\Response;
use PivotPHP\Core\Http\Pool\Psr7Pool;
use PivotPHP\Core\Exceptions\HttpException;

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
 * @package PivotPHP\Core\Routing
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
        Application $app,
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
            self::registerSingleFile($route, $file, $app);
        }
    }

    /**
     * Registra um único arquivo como rota estática
     * @param array{path: string, size: int, mime: string, extension: string} $fileInfo
     */
    private static function registerSingleFile(
        string $route,
        array $fileInfo,
        Application $app
    ): void {
        // Cria handler específico para este arquivo
        $handler = self::createFileHandler($fileInfo);

        // Registra no router
        $app->get($route, $handler);

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
     * @return callable(Request, Response): Response
     */
    private static function createFileHandler(array $fileInfo): callable
    {
        return function (Request $req, Response $res) use ($fileInfo) {
            // Suprime warning sobre $req não usado - pode ser usado em funcionalidades futuras
            unset($req);
            self::$stats['total_hits']++;

            // Lê conteúdo do arquivo
            $content = file_get_contents($fileInfo['path']);
            if ($content === false) {
                throw new HttpException(500, 'Cannot read file: ' . $fileInfo['path']);
            }

            // Headers de resposta
            $res = $res->withHeader('Content-Type', $fileInfo['mime'])
                      ->withHeader('Content-Length', (string)strlen($content));

            // Headers de cache
            $cacheMaxAge = self::$config['cache_control_max_age'];
            if (is_numeric($cacheMaxAge) && $cacheMaxAge > 0) {
                $maxAge = (int)$cacheMaxAge;
                $res = $res->withHeader('Cache-Control', "public, max-age={$maxAge}");
            }

            // ETag baseado no arquivo
            $filemtime = filemtime($fileInfo['path']);
            $etag = md5($fileInfo['path'] . ($filemtime !== false ? (string)$filemtime : '0') . $fileInfo['size']);
            $res = $res->withHeader('ETag', '"' . $etag . '"');

            // Last-Modified
            $filemtime = filemtime($fileInfo['path']);
            $lastModified = gmdate('D, d M Y H:i:s', $filemtime !== false ? $filemtime : 0) . ' GMT';
            $res = $res->withHeader('Last-Modified', $lastModified);

            // Define o body e retorna response
            $res = $res->withBody(Psr7Pool::getStream($content));
            return $res;
        };
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
