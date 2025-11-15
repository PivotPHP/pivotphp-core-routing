<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Cache;

use PivotPHP\Routing\Contracts\FileCacheInterface;
use RuntimeException;

/**
 * File-based Cache Strategy
 *
 * Implements PSR-6/PSR-16 compliant file-based caching for routes.
 */
class FileCacheStrategy implements FileCacheInterface
{
    private string $cacheDirectory;
    private string $cacheFile = 'routes_compiled.php';
    private string $patternsFile = 'patterns.php';

    /** @var array<string, int> */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
    ];

    /**
     * @param string $cacheDirectory Directory for cache files
     */
    public function __construct(string $cacheDirectory)
    {
        $this->cacheDirectory = rtrim($cacheDirectory, '/');
        $this->ensureCacheDirectoryExists();
    }

    /**
     * {@inheritDoc}
     */
    public function getCompiledPattern(string $pattern): ?string
    {
        $patterns = $this->loadPatternsCache();

        if (isset($patterns[$pattern])) {
            $this->stats['hits']++;
            return $patterns[$pattern];
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setCompiledPattern(string $pattern, string $compiled): void
    {
        $patterns = $this->loadPatternsCache();
        $patterns[$pattern] = $compiled;
        $this->savePatternsCache($patterns);
        $this->stats['writes']++;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): mixed
    {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            $this->stats['misses']++;
            return null;
        }

        if (!is_readable($filePath)) {
            error_log("Cache file not readable: {$filePath}");
            $this->stats['misses']++;
            return null;
        }

        try {
            $data = include $filePath;
            if ($data === false) {
                error_log("Failed to load cache file: {$filePath}");
                $this->stats['misses']++;
                return null;
            }
        } catch (\Throwable $e) {
            error_log("Error loading cache file {$filePath}: " . $e->getMessage());
            $this->stats['misses']++;
            return null;
        }

        $this->stats['hits']++;
        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $filePath = $this->getCacheFilePath($key);
        $data = var_export($value, true);
        $content = "<?php\n\nreturn {$data};\n";

        $bytesWritten = @file_put_contents($filePath, $content, LOCK_EX);
        if ($bytesWritten === false) {
            error_log("Failed to write cache file: {$filePath}. Check directory permissions.");
            throw new RuntimeException("Failed to write cache file: {$filePath}");
        }
        $this->stats['writes']++;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return file_exists($this->getCacheFilePath($key));
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        $filePath = $this->getCacheFilePath($key);

        if (file_exists($filePath)) {
            if (!@unlink($filePath)) {
                error_log("Failed to delete cache file: {$filePath}");
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $files = @glob($this->cacheDirectory . '/*.php');

        if ($files === false) {
            error_log("Failed to list cache files in: {$this->cacheDirectory}");
            return;
        }

        $failures = [];
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $failures[] = $file;
            }
        }

        if (!empty($failures)) {
            error_log("Failed to delete cache files: " . implode(', ', $failures));
        }

        $this->stats = ['hits' => 0, 'misses' => 0, 'writes' => 0];
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        $hitRate = 0.0;
        $total = $this->stats['hits'] + $this->stats['misses'];

        if ($total > 0) {
            $hitRate = ($this->stats['hits'] / $total) * 100;
        }

        return [
            'strategy' => 'file',
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['writes'],
            'hit_rate' => round($hitRate, 2),
            'cache_directory' => $this->cacheDirectory,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function warm(array $routes): void
    {
        $this->writeRoutesCache($routes);

        // Also cache patterns
        foreach ($routes as $route) {
            $pattern = $route['pattern'] ?? null;
            $compiledPattern = $route['compiled_pattern'] ?? null;

            if (is_string($pattern) && is_string($compiledPattern)) {
                $this->setCompiledPattern($pattern, $compiledPattern);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'file';
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheFilePath(string $key): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDirectory . '/' . $safeName . '.php';
    }

    /**
     * {@inheritDoc}
     */
    public function isCacheValid(string $key): bool
    {
        return file_exists($this->getCacheFilePath($key));
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheTime(string $key): ?int
    {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $mtime = @filemtime($filePath);
        if ($mtime === false) {
            error_log("Cannot get file modification time: {$filePath}");
            return null;
        }
        return $mtime;
    }

    /**
     * {@inheritDoc}
     */
    public function invalidate(string $key): void
    {
        $this->delete($key);
    }

    /**
     * {@inheritDoc}
     */
    public function writeRoutesCache(array $routes): void
    {
        $filePath = $this->cacheDirectory . '/' . $this->cacheFile;
        $data = var_export($routes, true);
        $content = "<?php\n\n// Generated route cache\n// " . date('Y-m-d H:i:s') . "\n\nreturn {$data};\n";

        $bytesWritten = @file_put_contents($filePath, $content, LOCK_EX);
        if ($bytesWritten === false) {
            error_log("Failed to write routes cache file: {$filePath}. Check directory permissions.");
            throw new RuntimeException("Failed to write routes cache file: {$filePath}");
        }
        $this->stats['writes']++;
    }

    /**
     * {@inheritDoc}
     */
    public function readRoutesCache(): ?array
    {
        $filePath = $this->cacheDirectory . '/' . $this->cacheFile;

        if (!file_exists($filePath)) {
            return null;
        }

        if (!is_readable($filePath)) {
            error_log("Routes cache file not readable: {$filePath}");
            return null;
        }

        try {
            $data = include $filePath;
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            error_log("Error loading routes cache {$filePath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    /**
     * {@inheritDoc}
     */
    public function setCacheDirectory(string $directory): void
    {
        $this->cacheDirectory = rtrim($directory, '/');
        $this->ensureCacheDirectoryExists();
    }

    /**
     * {@inheritDoc}
     */
    public function ensureCacheDirectoryExists(): bool
    {
        if (!is_dir($this->cacheDirectory)) {
            if (!@mkdir($this->cacheDirectory, 0755, true) && !is_dir($this->cacheDirectory)) {
                throw new RuntimeException("Failed to create cache directory: {$this->cacheDirectory}. Check permissions and disk space.");
            }
        }

        if (!is_writable($this->cacheDirectory)) {
            throw new RuntimeException("Cache directory is not writable: {$this->cacheDirectory}");
        }

        return true;
    }

    /**
     * Load patterns cache from file
     *
     * @return array<string, string>|null
     */
    /**
     * @return array<string, string>
     */
    private function loadPatternsCache(): array
    {
        $filePath = $this->cacheDirectory . '/' . $this->patternsFile;

        if (!file_exists($filePath)) {
            return [];
        }

        if (!is_readable($filePath)) {
            return [];
        }

        try {
            $data = include $filePath;
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            error_log("Error loading patterns cache {$filePath}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save patterns cache to file
     *
     * @param array<string, string> $patterns Patterns to save
     * @return void
     */
    private function savePatternsCache(array $patterns): void
    {
        $filePath = $this->cacheDirectory . '/' . $this->patternsFile;
        $data = var_export($patterns, true);
        $content = "<?php\n\n// Generated patterns cache\n// " . date('Y-m-d H:i:s') . "\n\nreturn {$data};\n";

        $bytesWritten = @file_put_contents($filePath, $content, LOCK_EX);
        if ($bytesWritten === false) {
            error_log("Failed to write patterns cache file: {$filePath}. Check directory permissions.");
            throw new RuntimeException("Failed to write patterns cache file: {$filePath}");
        }
    }
}

