<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\SimpleStaticFileManager;
use PivotPHP\Routing\Router\StaticFileManager;

class StaticFileManagerTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        Router::clear();
        StaticFileManager::clearCache();

        $this->testDir = sys_get_temp_dir() . '/pivotphp-static-test-' . uniqid();
        mkdir($this->testDir, 0777, true);
        file_put_contents($this->testDir . '/app.js', 'console.log(1);');
        file_put_contents($this->testDir . '/site.css', 'body{}');
        mkdir($this->testDir . '/sub', 0777, true);
        file_put_contents($this->testDir . '/sub/nested.txt', 'nested');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->testDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testRegistersEachFileAsRoute(): void
    {
        StaticFileManager::registerDirectory('/assets', $this->testDir);

        $files = StaticFileManager::getRegisteredFiles();
        $this->assertContains('/assets/app.js', $files);
        $this->assertContains('/assets/site.css', $files);
        $this->assertContains('/assets/sub/nested.txt', $files);

        $this->assertNotNull(Router::identify('GET', '/assets/app.js'));
    }

    public function testThrowsWhenDirectoryMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StaticFileManager::registerDirectory('/assets', '/does/not/exist');
    }

    public function testRegisteredPathsAndPathInfo(): void
    {
        StaticFileManager::registerDirectory('/assets', $this->testDir);

        $this->assertContains('/assets', StaticFileManager::getRegisteredPaths());

        $info = StaticFileManager::getPathInfo('/assets');
        $this->assertIsArray($info);
        $this->assertArrayHasKey('physical_path', $info);
        $this->assertArrayHasKey('options', $info);

        $this->assertNull(StaticFileManager::getPathInfo('/nope'));
    }

    public function testStats(): void
    {
        StaticFileManager::registerDirectory('/assets', $this->testDir);

        $stats = StaticFileManager::getStats();
        $this->assertSame(1, $stats['registered_paths']);
        $this->assertGreaterThanOrEqual(3, $stats['registered_files']);
    }

    public function testListFilesAndRouteMap(): void
    {
        StaticFileManager::registerDirectory('/assets', $this->testDir);

        $this->assertNotEmpty(StaticFileManager::listFiles('/assets'));
        $this->assertSame([], StaticFileManager::listFiles('/nope'));

        $map = StaticFileManager::generateRouteMap();
        $this->assertArrayHasKey('/assets', $map);
        $this->assertSame(3, $map['/assets']['file_count']);
    }

    public function testClearCacheResetsState(): void
    {
        StaticFileManager::registerDirectory('/assets', $this->testDir);
        StaticFileManager::clearCache();

        $this->assertSame([], StaticFileManager::getRegisteredFiles());
        $this->assertSame([], StaticFileManager::getRegisteredPaths());
        $this->assertSame(0, StaticFileManager::getStats()['registered_files']);
    }

    public function testConfigureDoesNotThrow(): void
    {
        StaticFileManager::configure(['max_file_size' => 5]);
        StaticFileManager::configure(['max_file_size' => 10485760]);

        $this->assertTrue(true);
    }

    public function testSimpleStaticFileManagerIsTheSameImplementation(): void
    {
        $this->assertTrue(is_subclass_of(SimpleStaticFileManager::class, StaticFileManager::class));

        StaticFileManager::registerDirectory('/assets', $this->testDir);

        // Uma única implementação: o "Simple" lê o mesmo estado do Static.
        $this->assertContains('/assets/app.js', SimpleStaticFileManager::getRegisteredFiles());
    }
}
