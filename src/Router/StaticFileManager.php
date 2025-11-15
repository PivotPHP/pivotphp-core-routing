<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Router;

use PivotPHP\Core\Exceptions\HttpException;

/**
 * Static File Manager (Façade + Advanced Features)
 *
 * Implementação avançada para servir arquivos estáticos com cache e otimizações.
 * Funcionalidade similar ao express.static() do Node.js.
 *
 * ESTRATÉGIA: Resolve arquivos dinamicamente com cache inteligente.
 * - Usa padrões de rota com wildcards
 * - Cache inteligente de metadados de arquivos
 * - Funcionalidades avançadas: ETag, compression, security
 * - Suporte a index files (index.html, index.htm)
 *
 * USO RECOMENDADO:
 * - Projetos médios/grandes com centenas de arquivos estáticos
 * - Quando você quer funcionalidades express.static()
 * - SPAs com assets e bundle management
 * - Produção com cache e performance otimizada
 *
 * ARQUITETURA:
 * - registerDirectory() → Delega para SimpleStaticFileManager
 * - register() → Mantém compatibilidade com método antigo
 * - Funcionalidades extras: listFiles(), generateRouteMap(), cache management
 *
 * @package PivotPHP\Core\Routing
 * @since 1.1.3
 */
class StaticFileManager
{
    /**
     * Cache de arquivos mapeados
     * @var array<string, array{path: string, mime: string, size: int, modified: int}>
     */
    private static array $fileCache = [];

    /**
     * Pastas registradas
     * @var array<string, array{physical_path: string, options: array}>
     */
    private static array $registeredPaths = [];

    /**
     * Estatísticas de uso
     * @var array<string, mixed>
     */
    private static array $stats = [
        'registered_paths' => 0,
        'cached_files' => 0,
        'total_hits' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'memory_usage_bytes' => 0
    ];

    /**
     * Configurações
     * @var array{
     *     enable_cache: bool,
     *     max_file_size: int,
     *     max_cache_entries: int,
     *     allowed_extensions: array<int, string>,
     *     security_check: bool,
     *     send_etag: bool,
     *     send_last_modified: bool,
     *     cache_control_max_age: int
     * }
     */
    private static array $config = [
        'enable_cache' => true,
        'max_file_size' => 10485760,    // 10MB máximo
        'max_cache_entries' => 10000,   // Máximo de arquivos no cache
        'allowed_extensions' => [
            'js', 'css', 'html', 'htm', 'json', 'xml',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
            'woff', 'woff2', 'ttf', 'eot',
            'pdf', 'txt', 'md'
        ],
        'security_check' => true,       // Previne path traversal
        'send_etag' => true,           // Headers de cache
        'send_last_modified' => true,
        'cache_control_max_age' => 86400 // 24 horas
    ];

    /**
     * MIME types para arquivos comuns
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
     * Registra um diretório inteiro, criando rotas individuais para cada arquivo
     * @param array<string, mixed> $options
     */
    public static function registerDirectory(
        string $routePrefix,
        string $physicalPath,
        \PivotPHP\Core\Core\Application $app,
        array $options = []
    ): void {
        // Delega para o SimpleStaticFileManager
        \PivotPHP\Core\Routing\SimpleStaticFileManager::registerDirectory($routePrefix, $physicalPath, $app, $options);
    }

    /**
     * Registra uma pasta para servir arquivos estáticos (método antigo - mantido para compatibilidade)
     *
     * @param string $routePrefix Prefixo da rota (ex: '/public/js')
     * @param string $physicalPath Pasta física (ex: 'src/bundle/js')
     * @param array<string, mixed> $options Opções adicionais
     * @return callable(\PivotPHP\Core\Http\Request, \PivotPHP\Core\Http\Response): \PivotPHP\Core\Http\Response
     * @deprecated Use registerDirectory() no lugar
     */
    public static function register(
        string $routePrefix,
        string $physicalPath,
        array $options = []
    ): callable {
        // Normaliza caminhos
        $routePrefix = '/' . trim($routePrefix, '/');
        $physicalPath = rtrim($physicalPath, '/\\');

        // Valida que pasta existe
        if (!is_dir($physicalPath)) {
            throw new \InvalidArgumentException("Physical path does not exist: {$physicalPath}");
        }

        // Valida que pasta é legível
        if (!is_readable($physicalPath)) {
            throw new \InvalidArgumentException("Physical path is not readable: {$physicalPath}");
        }

        // Registra o mapeamento
        $realPath = realpath($physicalPath);
        if ($realPath === false) {
            throw new \InvalidArgumentException("Cannot resolve real path for: {$physicalPath}");
        }

        self::$registeredPaths[$routePrefix] = [
            'physical_path' => $realPath,
            'options' => array_merge(
                [
                    'index' => ['index.html', 'index.htm'],
                    'dotfiles' => 'ignore',  // ignore, allow, deny
                    'extensions' => false,   // auto-append extensions
                    'fallthrough' => true,   // continue to next middleware on miss
                    'redirect' => true       // redirect trailing slash
                ],
                $options
            )
        ];

        self::$stats['registered_paths']++;

        // Retorna handler que resolve arquivos
        return self::createFileHandler($routePrefix);
    }

    /**
     * Cria handler otimizado para servir arquivos
     * @return callable(\PivotPHP\Core\Http\Request, \PivotPHP\Core\Http\Response): \PivotPHP\Core\Http\Response
     */
    private static function createFileHandler(string $routePrefix): callable
    {
        return function (
            \PivotPHP\Core\Http\Request $req,
            \PivotPHP\Core\Http\Response $res
        ) use ($routePrefix): \PivotPHP\Core\Http\Response {
            // Extrai filepath do path da requisição removendo o prefixo
            $requestPath = $req->getPathCallable();

            // Remove o prefixo da rota para obter o caminho relativo do arquivo
            if (!str_starts_with($requestPath, $routePrefix)) {
                throw new HttpException(404, 'Path does not match route prefix');
            }

            $relativePath = substr($requestPath, strlen($routePrefix));
            if (empty($relativePath) || $relativePath === '/') {
                // Se não há filepath, tenta arquivos index
                $relativePath = '/';
            } else {
                $relativePath = '/' . ltrim($relativePath, '/');
            }

            // Resolve arquivo físico
            $fileInfo = self::resolveFile($routePrefix, $relativePath);

            if ($fileInfo === null) {
                throw new HttpException(404, 'File not found');
            }

            // Serve o arquivo
            return self::serveFile($fileInfo, $req, $res);
        };
    }

    /**
     * Resolve arquivo físico baseado na rota
     * @return array{path: string, mime: string, size: int, modified: int, extension: string}|null
     */
    private static function resolveFile(string $routePrefix, string $relativePath): ?array
    {
        if (!isset(self::$registeredPaths[$routePrefix])) {
            return null;
        }

        $config = self::$registeredPaths[$routePrefix];
        $physicalPath = $config['physical_path'];
        $options = $config['options'];

        // Security check: previne path traversal
        if (self::$config['security_check'] && self::containsPathTraversal($relativePath)) {
            return null;
        }

        // Constrói caminho físico
        $filePath = $physicalPath . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        // Se é diretório, procura index files
        if (is_dir($filePath)) {
            foreach ($options['index'] as $indexFile) {
                $indexPath = $filePath . DIRECTORY_SEPARATOR . $indexFile;
                if (file_exists($indexPath) && is_readable($indexPath)) {
                    $filePath = $indexPath;
                    break;
                }
            }

            // Se ainda é diretório após busca de index, retorna null
            if (is_dir($filePath)) {
                return null;
            }
        }

        // Verifica se arquivo existe e é legível
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        // Verifica extensão permitida
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::$config['allowed_extensions'], true)) {
            return null;
        }

        // Verifica tamanho do arquivo
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            return null; // Erro ao ler tamanho do arquivo
        }
        if ($fileSize > self::$config['max_file_size']) {
            return null;
        }

        // Verifica timestamp de modificação
        $fileModified = filemtime($filePath);
        if ($fileModified === false) {
            return null; // Erro ao ler timestamp do arquivo
        }

        // Determina MIME type
        $mimeType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        return [
            'path' => $filePath,
            'mime' => $mimeType,
            'size' => $fileSize,
            'modified' => $fileModified,
            'extension' => $extension
        ];
    }

    /**
     * Serve arquivo com headers otimizados
     * @param array{path: string, mime: string, size: int, modified: int, extension: string} $fileInfo
     */
    private static function serveFile(
        array $fileInfo,
        \PivotPHP\Core\Http\Request $req,
        \PivotPHP\Core\Http\Response $res
    ): \PivotPHP\Core\Http\Response {
        self::$stats['total_hits']++;

        // Headers de cache
        $res = $res->withHeader('Content-Type', $fileInfo['mime'])
                  ->withHeader('Content-Length', (string)$fileInfo['size']);

        if (self::$config['send_etag']) {
            $etag = md5($fileInfo['path'] . $fileInfo['modified'] . $fileInfo['size']);
            $res = $res->withHeader('ETag', '"' . $etag . '"');

            // Verifica If-None-Match (simplificado por enquanto)
            // $ifNoneMatch = $req->getHeader('If-None-Match');
            // if ($ifNoneMatch && trim($ifNoneMatch, '"') === $etag) {
            //     return $res->status(304); // Not Modified
            // }
        }

        if (self::$config['send_last_modified']) {
            $lastModified = gmdate('D, d M Y H:i:s', $fileInfo['modified']) . ' GMT';
            $res = $res->withHeader('Last-Modified', $lastModified);

            // Verifica If-Modified-Since (simplificado por enquanto)
            // $ifModifiedSince = $req->getHeader('If-Modified-Since');
            // if ($ifModifiedSince && strtotime($ifModifiedSince) >= $fileInfo['modified']) {
            //     return $res->status(304); // Not Modified
            // }
        }

        // Cache-Control
        if (self::$config['cache_control_max_age'] > 0) {
            $maxAge = (int) self::$config['cache_control_max_age'];
            $res = $res->withHeader('Cache-Control', "public, max-age=" . (string) $maxAge);
        }

        // Lê e envia conteúdo do arquivo
        $content = file_get_contents($fileInfo['path']);
        if ($content === false) {
            throw new HttpException(
                500,
                'Unable to read file: ' . $fileInfo['path'],
                ['Content-Type' => 'application/json']
            );
        }

        // Define o body e retorna response
        $res = $res->withBody(\PivotPHP\Core\Http\Pool\Psr7Pool::getStream($content));
        return $res;
    }

    /**
     * Verifica se path contém tentativas de path traversal
     */
    private static function containsPathTraversal(string $path): bool
    {
        return strpos($path, '..') !== false ||
               strpos($path, '\\') !== false ||
               strpos($path, '\0') !== false;
    }

    /**
     * Configura o manager
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config); // @phpstan-ignore-line
    }

    /**
     * Obtém estatísticas
     * @return array<string, mixed>
     */
    public static function getStats(): array
    {
        $memoryUsage = 0;
        foreach (self::$fileCache as $file) {
            $memoryUsage += strlen(serialize($file));
        }

        return array_merge(
            self::$stats,
            [
                'memory_usage_bytes' => $memoryUsage,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 3)
            ]
        );
    }

    /**
     * Lista caminhos registrados
     * @return array<int, string>
     */
    public static function getRegisteredPaths(): array
    {
        return array_keys(self::$registeredPaths);
    }

    /**
     * Obtém informações de um caminho registrado
     * @return array{physical_path: string, options: array<string, mixed>}|null
     */
    public static function getPathInfo(string $routePrefix): ?array
    {
        return self::$registeredPaths[$routePrefix] ?? null;
    }

    /**
     * Limpa cache
     */
    public static function clearCache(): void
    {
        self::$fileCache = [];
        self::$stats['cached_files'] = 0;
        self::$stats['cache_hits'] = 0;
        self::$stats['cache_misses'] = 0;
    }

    /**
     * Lista arquivos disponíveis em uma pasta registrada
     * @return array<int, array{path: string, physical_path: string, size: int, modified: int, extension: string, mime: string}>
     */
    public static function listFiles(
        string $routePrefix,
        string $subPath = '',
        int $maxDepth = 3
    ): array {
        if (!isset(self::$registeredPaths[$routePrefix])) {
            return [];
        }

        $config = self::$registeredPaths[$routePrefix];
        $basePath = $config['physical_path'];
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

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, self::$config['allowed_extensions'], true)) {
                    $relativePath = str_replace($basePath, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    $files[] = [
                        'path' => $routePrefix . $relativePath,
                        'physical_path' => $file->getPathname(),
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                        'extension' => $extension,
                        'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream'
                    ];
                }
            }
        }

        return $files;
    }

    /**
     * Gera mapa de todas as rotas de arquivos estáticos
     * @return array<string, array{physical_path: string, file_count: int, files: array<int, array{path: string, physical_path: string, size: int, modified: int, extension: string, mime: string}>}>
     */
    public static function generateRouteMap(): array
    {
        $map = [];

        foreach (self::$registeredPaths as $routePrefix => $config) {
            $files = self::listFiles($routePrefix);
            $map[$routePrefix] = [
                'physical_path' => $config['physical_path'],
                'file_count' => count($files),
                'files' => $files
            ];
        }

        return $map;
    }
}
