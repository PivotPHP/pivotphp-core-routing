<?php

namespace PivotPHP\Routing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\RouteCache;
use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Http\Response;

class RegexRoutingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Router::clear();
        RouteCache::clear();
    }

    protected function tearDown(): void
    {
        Router::clear();
        RouteCache::clear();
    }

    /**
     * @test
     */
    public function testSimpleNumericConstraint(): void
    {
        Router::get(
            '/users/:id<\d+>',
            function (Request $req, Response $res) {
                return $res->json(['user_id' => $req->param('id')]);
            }
        );

        // Deve corresponder
        $route = Router::identify('GET', '/users/123');
        $this->assertNotNull($route);
        $this->assertEquals('/users/:id<\d+>', $route['path']);

        // Não deve corresponder (letras)
        $route = Router::identify('GET', '/users/abc');
        $this->assertNull($route);
    }

    /**
     * @test
     */
    public function testSlugConstraint(): void
    {
        Router::get(
            '/posts/:slug<slug>',
            function (Request $req, Response $res) {
                return $res->json(['slug' => $req->param('slug')]);
            }
        );

        // Deve corresponder
        $route = Router::identify('GET', '/posts/my-awesome-post-123');
        $this->assertNotNull($route);

        // Não deve corresponder (maiúsculas)
        $route = Router::identify('GET', '/posts/My-Awesome-Post');
        $this->assertNull($route);

        // Não deve corresponder (caracteres especiais)
        $route = Router::identify('GET', '/posts/my_post!');
        $this->assertNull($route);
    }

    /**
     * @test
     */
    public function testDateConstraints(): void
    {
        Router::get(
            '/archive/:year<year>/:month<month>/:day<day>',
            function (Request $req, Response $res) {
                return $res->json(
                    [
                        'year' => $req->param('year'),
                        'month' => $req->param('month'),
                        'day' => $req->param('day')
                    ]
                );
            }
        );

        // Deve corresponder
        $route = Router::identify('GET', '/archive/2024/01/15');
        $this->assertNotNull($route);

        // Não deve corresponder (ano inválido)
        $route = Router::identify('GET', '/archive/24/01/15');
        $this->assertNull($route);

        // Não deve corresponder (mês inválido)
        $route = Router::identify('GET', '/archive/2024/1/15');
        $this->assertNull($route);
    }

    /**
     * @test
     */
    public function testUUIDConstraint(): void
    {
        Router::get(
            '/api/resources/:uuid<uuid>',
            function (Request $req, Response $res) {
                return $res->json(['uuid' => $req->param('uuid')]);
            }
        );

        // UUID válido
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';
        $route = Router::identify('GET', "/api/resources/{$validUuid}");
        $this->assertNotNull($route);

        // UUID inválido (maiúsculas)
        $invalidUuid = '550E8400-E29B-41D4-A716-446655440000';
        $route = Router::identify('GET', "/api/resources/{$invalidUuid}");
        $this->assertNull($route);

        // UUID inválido (formato errado)
        $route = Router::identify('GET', '/api/resources/not-a-uuid');
        $this->assertNull($route);
    }

    /**
     * @test
     */
    public function testFileExtensionConstraint(): void
    {
        Router::get(
            '/files/:filename<[\w-]+>.:ext<jpg|png|gif|webp>',
            function (Request $req, Response $res) {
                return $res->json(
                    [
                        'filename' => $req->param('filename'),
                        'extension' => $req->param('ext')
                    ]
                );
            }
        );

        // Extensões válidas
        $validExtensions = ['jpg', 'png', 'gif', 'webp'];
        foreach ($validExtensions as $ext) {
            $route = Router::identify('GET', "/files/my-image.{$ext}");
            $this->assertNotNull($route, "Failed for extension: {$ext}");
        }

        // Extensões inválidas
        $invalidExtensions = ['pdf', 'doc', 'exe'];
        foreach ($invalidExtensions as $ext) {
            $route = Router::identify('GET', "/files/my-file.{$ext}");
            $this->assertNull($route, "Should not match extension: {$ext}");
        }
    }

    /**
     * @test
     */
    public function testComplexEmailPattern(): void
    {
        Router::get(
            '/contact/:email<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}>',
            function (Request $req, Response $res) {
                return $res->json(['email' => $req->param('email')]);
            }
        );

        // Emails válidos
        $validEmails = [
            'user@example.com',
            'john.doe+tag@company.co.uk',
            'test_123@sub.domain.org'
        ];

        foreach ($validEmails as $email) {
            $route = Router::identify('GET', '/contact/' . $email);
            $this->assertNotNull($route, "Failed for email: {$email}");
        }

        // Emails inválidos
        $invalidEmails = [
            'invalid.email',
            '@example.com',
            'user@',
            'user@.com'
        ];

        foreach ($invalidEmails as $email) {
            $route = Router::identify('GET', '/contact/' . $email);
            $this->assertNull($route, "Should not match email: {$email}");
        }
    }

    /**
     * @test
     */
    public function testMultipleConstrainedRoutes(): void
    {
        // Rotas com diferentes constraints para o mesmo path base
        Router::get(
            '/items/:id<\d+>',
            function (Request $req, Response $res) {
                return $res->json(['type' => 'numeric', 'id' => $req->param('id')]);
            }
        );

        Router::get(
            '/items/:slug<slug>',
            function (Request $req, Response $res) {
                return $res->json(['type' => 'slug', 'slug' => $req->param('slug')]);
            }
        );

        // Deve corresponder à rota numérica
        $route = Router::identify('GET', '/items/123');
        $this->assertNotNull($route);
        $this->assertEquals('/items/:id<\d+>', $route['path']);

        // Deve corresponder à rota slug
        $route = Router::identify('GET', '/items/my-item');
        $this->assertNotNull($route);
        $this->assertEquals('/items/:slug<slug>', $route['path']);
    }

    /**
     * @test
     */
    public function testISBNPattern(): void
    {
        Router::get(
            '/books/:isbn<\d{3}-\d{10}>',
            function (Request $req, Response $res) {
                return $res->json(['isbn' => $req->param('isbn')]);
            }
        );

        // ISBN válido
        $route = Router::identify('GET', '/books/978-0123456789');
        $this->assertNotNull($route);

        // ISBN inválido (formato errado)
        $invalidISBNs = [
            '9780123456789',    // Sem hífen
            '97-0123456789',    // Hífen na posição errada
            '978-012345678',    // Poucos dígitos
            '978-01234567890',  // Muitos dígitos
            'ABC-0123456789'    // Letras no prefixo
        ];

        foreach ($invalidISBNs as $isbn) {
            $route = Router::identify('GET', "/books/{$isbn}");
            $this->assertNull($route, "Should not match ISBN: {$isbn}");
        }
    }

    /**
     * @test
     */
    public function testVersionedAPIRoutes(): void
    {
        Router::get(
            '/api/:version<v\d+>/users',
            function (Request $req, Response $res) {
                return $res->json(['version' => $req->param('version')]);
            }
        );

        // Versões válidas
        $validVersions = ['v1', 'v2', 'v10', 'v123'];
        foreach ($validVersions as $version) {
            $route = Router::identify('GET', "/api/{$version}/users");
            $this->assertNotNull($route, "Failed for version: {$version}");
        }

        // Versões inválidas
        $invalidVersions = ['1', 'version1', 'v1.0', 'va', 'v'];
        foreach ($invalidVersions as $version) {
            $route = Router::identify('GET', "/api/{$version}/users");
            $this->assertNull($route, "Should not match version: {$version}");
        }
    }

    /**
     * @test
     */
    public function testBackwardCompatibility(): void
    {
        // Rotas antigas sem constraints devem continuar funcionando
        Router::get(
            '/old/route/:id',
            function (Request $req, Response $res) {
                return $res->json(['id' => $req->param('id')]);
            }
        );

        // Deve aceitar qualquer valor
        $testValues = ['123', 'abc', 'test-slug', 'special!chars'];
        foreach ($testValues as $value) {
            $route = Router::identify('GET', "/old/route/{$value}");
            $this->assertNotNull($route, "Backward compatibility failed for: {$value}");
        }
    }

    /**
     * @test
     */
    public function testRouteGroups(): void
    {
        Router::group(
            '/admin',
            function () {
                Router::get(
                    '/users/:id<\d+>',
                    function (Request $req, Response $res) {
                        return $res->json(['admin_user_id' => $req->param('id')]);
                    }
                );

                Router::get(
                    '/posts/:slug<slug>',
                    function (Request $req, Response $res) {
                        return $res->json(['admin_post_slug' => $req->param('slug')]);
                    }
                );
            }
        );

        // Deve funcionar com grupos
        $route = Router::identify('GET', '/admin/users/123');
        $this->assertNotNull($route);

        $route = Router::identify('GET', '/admin/users/abc');
        $this->assertNull($route);

        $route = Router::identify('GET', '/admin/posts/my-post');
        $this->assertNotNull($route);
    }

    /**
     * @test
     */
    public function testPerformanceWithManyConstrainedRoutes(): void
    {
        // Skip performance test when Xdebug is active (coverage mode)
        if (extension_loaded('xdebug') && xdebug_is_debugger_active()) {
            $this->markTestSkipped('Performance test skipped when Xdebug is active');
        }

        // Force complete cleanup before performance test
        Router::clear();
        RouteCache::clear();

        // Garbage collect to ensure clean state
        gc_collect_cycles();

        // Adiciona muitas rotas com constraints
        for ($i = 1; $i <= 100; $i++) {
            Router::get(
                "/route{$i}/:id<\d+>",
                function (Request $req, Response $res) use ($i) {
                    return $res->json(['route' => $i, 'id' => $req->param('id')]);
                }
            );
        }

        $startTime = microtime(true);

        // Testa identificação de rotas
        for ($i = 1; $i <= 100; $i++) {
            $route = Router::identify('GET', "/route{$i}/123");
            $this->assertNotNull($route);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Adjust timeout based on environment - more lenient for slow systems
        $isSlowEnvironment = extension_loaded('xdebug') ||
                           getenv('CI') === 'true' ||
                           getenv('GITHUB_ACTIONS') === 'true' ||
                           is_dir('/.dockerenv') ||
                           file_exists('/.dockerenv');

        $maxDuration = $isSlowEnvironment ? 30.0 : 0.5; // 30s for slow environments, 0.5s for fast
        $this->assertLessThan($maxDuration, $duration, "Route matching is too slow: {$duration}s");
    }
}
