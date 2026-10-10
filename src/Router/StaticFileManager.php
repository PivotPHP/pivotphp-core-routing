<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use Psr\Http\Message\ResponseInterface;

/**
 * Servidor de arquivos estáticos.
 *
 * Estratégia única: **registra cada arquivo do diretório como uma rota**
 * (`Router::add('GET', ...)`) — sem wildcards/regex. Uma implementação só
 * (consolidação de `SimpleStaticFileManager` — SPEC-095).
 *
 * @phpstan-type StaticFileInfo array{
 *     path: string,
 *     physical_path: string,
 *     size: int,
 *     modified: int,
 *     extension: string,
 *     mime: string
 * }
 */
class StaticFileManager
{
    /**
     * Arquivos registrados: rota => metadados.
     *
     * @var array<string, array{path: string, mime: string, size: int}>
     */
    private static array $registeredFiles = [];

    /**
     * Diretórios registrados: prefixo => caminho físico + opções.
     *
     * @var array<string, array{physical_path: string, options: array<string, mixed>}>
     */
    private static array $registeredPaths = [];

    /**
     * @var array<string, int>
     */
    private static array $stats = [
        'registered_paths' => 0,
        'registered_files' => 0,
        'total_hits' => 0,
        'memory_usage_bytes' => 0,
    ];

    /**
     * @var array<string, mixed>
     */
    private static array $config = [
        'max_file_size' => 10485760, // 10MB
        'allowed_extensions' => [
            'js', 'css', 'html', 'htm', 'json', 'xml',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
            'woff', 'woff2', 'ttf', 'eot',
            'pdf', 'txt', 'md',
        ],
        'cache_control_max_age' => 86400, // 24 horas
        'send_etag' => true,
        'send_last_modified' => true,
    ];

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
        'md' => 'text/markdown',
    ];

    /**
     * Registra um diretório inteiro, criando uma rota para cada arquivo.
     *
     * @param array<string, mixed> $options
     */
    public static function registerDirectory(
        string $routePrefix,
        string $physicalPath,
        array $options = []
    ): void {
        if (!is_dir($physicalPath)) {
            throw new \InvalidArgumentException("Directory does not exist: {$physicalPath}");
        }

        $routePrefix = '/' . trim($routePrefix, '/');
        $physicalPath = rtrim($physicalPath, '/\\');
        $realPath = realpath($physicalPath);

        self::$registeredPaths[$routePrefix] = [
            'physical_path' => $realPath !== false ? $realPath : $physicalPath,
            'options' => $options,
        ];
        self::$stats['registered_paths']++;

        foreach (self::scanDirectory($physicalPath) as $file) {
            $relativePath = str_replace($physicalPath, '', $file['path']);
            $relativePath = str_replace('\\', '/', $relativePath);

            self::registerSingleFile($routePrefix . $relativePath, $file);
        }
    }

    /**
     * @deprecated Use {@see self::registerDirectory()} (consolidação — SPEC-095). Mantido
     * apenas para compatibilidade de assinatura; não retorna mais um handler de prefixo.
     *
     * @param array<string, mixed> $options
     * @return callable
     */
    public static function register(string $routePrefix, string $physicalPath, array $options = []): callable
    {
        self::registerDirectory($routePrefix, $physicalPath, $options);

        return static fn ($req, $res) => $res;
    }

    /**
     * @param array{path: string, size: int, mime: string, extension: string} $fileInfo
     */
    private static function registerSingleFile(string $route, array $fileInfo): void
    {
        if (isset(self::$registeredFiles[$route])) {
            return;
        }

        Router::add('GET', $route, self::createFileHandler($fileInfo));

        self::$registeredFiles[$route] = [
            'path' => $fileInfo['path'],
            'mime' => $fileInfo['mime'],
            'size' => $fileInfo['size'],
        ];

        self::$stats['registered_files']++;
        self::$stats['memory_usage_bytes'] += $fileInfo['size'];
    }

    /**
     * @param array{path: string, size: int, mime: string, extension: string} $fileInfo
     * @return callable
     */
    private static function createFileHandler(array $fileInfo): callable
    {
        return static function ($req, $res) use ($fileInfo) {
            $response = self::toPsr7($res);
            self::$stats['total_hits']++;

            $content = file_get_contents($fileInfo['path']);
            if ($content === false) {
                throw new \RuntimeException('Cannot read file: ' . $fileInfo['path']);
            }

            $response = $response
                ->withHeader('Content-Type', $fileInfo['mime'])
                ->withHeader('Content-Length', (string) strlen($content));

            $cacheMaxAge = self::$config['cache_control_max_age'];
            if (is_numeric($cacheMaxAge) && $cacheMaxAge > 0) {
                $maxAge = (int) $cacheMaxAge;
                $response = $response->withHeader('Cache-Control', "public, max-age={$maxAge}");
            }

            $filemtime = filemtime($fileInfo['path']);

            if (self::$config['send_etag'] === true) {
                $etag = md5($fileInfo['path'] . ($filemtime !== false ? (string) $filemtime : '0') . $fileInfo['size']);
                $response = $response->withHeader('ETag', '"' . $etag . '"');
            }

            if (self::$config['send_last_modified'] === true) {
                $lastModified = gmdate('D, d M Y H:i:s', $filemtime !== false ? $filemtime : 0) . ' GMT';
                $response = $response->withHeader('Last-Modified', $lastModified);
            }

            // Escreve no stream do response PSR-7 (mutável) e retorna
            $response->getBody()->write($content);

            return $response;
        };
    }

    /**
     * Normaliza para uma resposta PSR-7 (aceita PSR-7 ou uma fachada com `psr7()`).
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
     * @return array<int, array{path: string, size: int, mime: string, extension: string}>
     */
    private static function scanDirectory(string $path): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $allowedExtensions = self::$config['allowed_extensions'];
        $maxFileSizeConfig = self::$config['max_file_size'];
        $maxFileSize = is_numeric($maxFileSizeConfig) ? (int) $maxFileSizeConfig : 10485760;

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if (!is_array($allowedExtensions) || !in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            if ($file->getSize() > $maxFileSize) {
                continue;
            }

            $files[] = [
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
                'extension' => $extension,
            ];
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * @return array<string, int>
     */
    public static function getStats(): array
    {
        return self::$stats;
    }

    /**
     * @return array<int, string>
     */
    public static function getRegisteredPaths(): array
    {
        return array_keys(self::$registeredPaths);
    }

    /**
     * @return array{physical_path: string, options: array<string, mixed>}|null
     */
    public static function getPathInfo(string $routePrefix): ?array
    {
        return self::$registeredPaths[$routePrefix] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function getRegisteredFiles(): array
    {
        return array_keys(self::$registeredFiles);
    }

    /**
     * Lista arquivos disponíveis num diretório registrado.
     *
     * @return array<int, StaticFileInfo>
     */
    public static function listFiles(
        string $routePrefix,
        string $subPath = '',
        int $maxDepth = 3
    ): array {
        if (!isset(self::$registeredPaths[$routePrefix])) {
            return [];
        }

        $basePath = self::$registeredPaths[$routePrefix]['physical_path'];
        $searchPath = $basePath . DIRECTORY_SEPARATOR . ltrim($subPath, '/\\');

        if (!is_dir($searchPath) || $maxDepth <= 0) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $iterator->setMaxDepth($maxDepth);

        $routePrefix = '/' . trim($routePrefix, '/');

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $allowed = self::$config['allowed_extensions'];
            if (!is_array($allowed) || !in_array($extension, $allowed, true)) {
                continue;
            }

            $relativePath = str_replace([$basePath, '\\'], ['', '/'], $file->getPathname());

            $files[] = [
                'path' => $routePrefix . $relativePath,
                'physical_path' => $file->getPathname(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'extension' => $extension,
                'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
            ];
        }

        return $files;
    }

    /**
     * Mapa de todas as rotas de arquivos estáticos.
     *
     * @return array<string, array{physical_path: string, file_count: int, files: array<int, StaticFileInfo>}>
     */
    public static function generateRouteMap(): array
    {
        $map = [];

        foreach (self::$registeredPaths as $routePrefix => $info) {
            $files = self::listFiles($routePrefix);
            $map[$routePrefix] = [
                'physical_path' => $info['physical_path'],
                'file_count' => count($files),
                'files' => $files,
            ];
        }

        return $map;
    }

    /**
     * Limpa o estado (arquivos e estatísticas).
     */
    public static function clearCache(): void
    {
        self::$registeredFiles = [];
        self::$registeredPaths = [];
        self::$stats = [
            'registered_paths' => 0,
            'registered_files' => 0,
            'total_hits' => 0,
            'memory_usage_bytes' => 0,
        ];
    }
}
