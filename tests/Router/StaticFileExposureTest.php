<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Router;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\StaticFileManager;

/**
 * Static files must not expose hidden files or files outside the public directory (SPEC-062).
 */
final class StaticFileExposureTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        Router::clear();
        StaticFileManager::clearCache();

        $this->base = sys_get_temp_dir() . '/pivotphp-static-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/public/css', 0777, true);
        mkdir($this->base . '/public/.git', 0777, true);
        mkdir($this->base . '/public/.well-known', 0777, true);
        mkdir($this->base . '/outside', 0777, true);

        file_put_contents($this->base . '/public/css/app.css', 'body{}');
        file_put_contents($this->base . '/public/.secrets.json', '{"key":"secret"}');
        file_put_contents($this->base . '/public/.git/config.json', '{}');
        file_put_contents($this->base . '/public/.well-known/security.txt', 'contact');
        file_put_contents($this->base . '/outside/secret.json', '{"outside":true}');
        symlink($this->base . '/outside/secret.json', $this->base . '/public/leak.json');
        symlink($this->base . '/public/css/app.css', $this->base . '/public/alias.css');
    }

    protected function tearDown(): void
    {
        Router::clear();
        StaticFileManager::clearCache();
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    /**
     * @return list<string>
     */
    private function registeredRoutes(): array
    {
        StaticFileManager::registerDirectory('/public', $this->base . '/public');

        $paths = array_column(Router::getRoutes(), 'path');
        sort($paths);

        return $paths;
    }

    public function testHiddenFilesAndDirectoriesAreNotPublished(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertNotContains('/public/.secrets.json', $routes);
        $this->assertNotContains('/public/.git/config.json', $routes);
        $this->assertNotContains('/public/.well-known/security.txt', $routes);
    }

    public function testSymlinkPointingOutsideTheDirectoryIsNotPublished(): void
    {
        $this->assertNotContains('/public/leak.json', $this->registeredRoutes());
    }

    public function testRegularFilesAndInternalSymlinksArePublished(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertContains('/public/css/app.css', $routes);
        $this->assertContains('/public/alias.css', $routes);
    }
}
