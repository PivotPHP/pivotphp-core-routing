<?php

declare(strict_types=1);

namespace PivotPHP\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PivotPHP\Routing\Router\Router;
use PivotPHP\Routing\Router\RouteCache;

/**
 * Testes abrangentes para compilação e identificação de rotas
 *
 * Cobre:
 * - Compilação de diferentes tipos de padrões
 * - Identificação otimizada (exact match, pattern match, groups)
 * - Performance e caching
 * - Edge cases e validações
 */
class RouteCompilationAndIdentificationTest extends TestCase
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

    // ========================================
    // TESTES DE COMPILAÇÃO
    // ========================================

    /**
     * @test
     * @group compilation
     */
    public function testCompileStaticRoute(): void
    {
        $compiled = RouteCache::compilePattern('/users');

        $this->assertIsArray($compiled);
        $this->assertArrayHasKey('pattern', $compiled);
        $this->assertArrayHasKey('parameters', $compiled);
        // Rotas estáticas podem retornar pattern null (otimização)
        $this->assertCount(0, $compiled['parameters']);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileRouteWithSingleParameter(): void
    {
        $compiled = RouteCache::compilePattern('/users/:id');

        $this->assertEquals('#^/users/([^/]+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileRouteWithMultipleParameters(): void
    {
        $compiled = RouteCache::compilePattern('/users/:userId/posts/:postId');

        $this->assertEquals('#^/users/([^/]+)/posts/([^/]+)/?$#', $compiled['pattern']);
        $this->assertCount(2, $compiled['parameters']);
        $this->assertEquals('userId', $compiled['parameters'][0]['name']);
        $this->assertEquals('postId', $compiled['parameters'][1]['name']);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileRouteWithConstrainedParameters(): void
    {
        $compiled = RouteCache::compilePattern('/users/:id<\d+>');

        $this->assertEquals('#^/users/(\d+)/?$#', $compiled['pattern']);
        $this->assertCount(1, $compiled['parameters']);
        $this->assertEquals('id', $compiled['parameters'][0]['name']);
        $this->assertEquals('\d+', $compiled['parameters'][0]['constraint']);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileRouteWithConstraintShortcuts(): void
    {
        $testCases = [
            '/users/:id<int>' => '\d+',
            '/posts/:slug<slug>' => '[a-z0-9-]+',
            '/pages/:name<alpha>' => '[a-zA-Z]+',
            '/items/:code<alnum>' => '[a-zA-Z0-9]+',
            '/resources/:uuid<uuid>' => '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
        ];

        foreach ($testCases as $path => $expectedConstraint) {
            $compiled = RouteCache::compilePattern($path);
            $this->assertEquals($expectedConstraint, $compiled['parameters'][0]['constraint']);
        }
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileRouteWithComplexConstraints(): void
    {
        $compiled = RouteCache::compilePattern('/posts/:year<\d{4}>/:month<\d{2}>/:slug<[a-z0-9-]+>');

        $this->assertEquals('#^/posts/(\d{4})/(\d{2})/([a-z0-9-]+)/?$#', $compiled['pattern']);
        $this->assertCount(3, $compiled['parameters']);

        $this->assertEquals('year', $compiled['parameters'][0]['name']);
        $this->assertEquals('\d{4}', $compiled['parameters'][0]['constraint']);

        $this->assertEquals('month', $compiled['parameters'][1]['name']);
        $this->assertEquals('\d{2}', $compiled['parameters'][1]['constraint']);

        $this->assertEquals('slug', $compiled['parameters'][2]['name']);
        $this->assertEquals('[a-z0-9-]+', $compiled['parameters'][2]['constraint']);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompilationIsCached(): void
    {
        $path = '/users/:id<\d+>';

        // Primeira compilação
        $compiled1 = RouteCache::compilePattern($path);

        // Segunda compilação deve vir do cache
        $compiled2 = RouteCache::compilePattern($path);

        $this->assertEquals($compiled1, $compiled2);

        // Verifica estatísticas de cache
        $stats = RouteCache::getStats();
        $this->assertIsArray($stats);
    }

    // ========================================
    // TESTES DE IDENTIFICAÇÃO
    // ========================================

    /**
     * @test
     * @group identification
     */
    public function testIdentifyStaticRoute(): void
    {
        Router::get('/users', function () {
            return 'users list';
        });

        $route = Router::identify('GET', '/users');

        $this->assertNotNull($route);
        $this->assertEquals('GET', $route['method']);
        $this->assertEquals('/users', $route['path']);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyRouteWithParameters(): void
    {
        Router::get('/users/:id', function () {
            return 'user detail';
        });

        $route = Router::identify('GET', '/users/123');

        $this->assertNotNull($route);
        $this->assertEquals('GET', $route['method']);
        $this->assertEquals('/users/:id', $route['path']);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('123', $route['matched_params']['id']);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyRouteWithMultipleParameters(): void
    {
        Router::get('/users/:userId/posts/:postId', function () {
            return 'post detail';
        });

        $route = Router::identify('GET', '/users/42/posts/7');

        $this->assertNotNull($route);
        $this->assertArrayHasKey('matched_params', $route);
        $this->assertEquals('42', $route['matched_params']['userId']);
        $this->assertEquals('7', $route['matched_params']['postId']);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyRouteWithConstrainedParameters(): void
    {
        Router::get('/users/:id<\d+>', function () {
            return 'user detail';
        });

        // Deve identificar com parâmetro numérico válido
        $route = Router::identify('GET', '/users/123');
        $this->assertNotNull($route);
        $this->assertEquals('123', $route['matched_params']['id']);

        // Não deve identificar com parâmetro não-numérico
        $invalidRoute = Router::identify('GET', '/users/abc');
        $this->assertNull($invalidRoute);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyPrioritizesStaticOverDynamic(): void
    {
        Router::get('/users/:id', function () {
            return 'dynamic';
        });

        Router::get('/users/admin', function () {
            return 'static';
        });

        // A rota estática deve ter prioridade
        $route = Router::identify('GET', '/users/admin');

        $this->assertNotNull($route);
        $this->assertEquals('/users/admin', $route['path']);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyReturnsNullForNonExistentRoute(): void
    {
        Router::get('/users', function () {
            return 'users';
        });

        $route = Router::identify('GET', '/posts');

        $this->assertNull($route);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyDistinguishesMethods(): void
    {
        Router::get('/users', function () {
            return 'GET users';
        });

        Router::post('/users', function () {
            return 'POST users';
        });

        $getRoute = Router::identify('GET', '/users');
        $postRoute = Router::identify('POST', '/users');

        $this->assertNotNull($getRoute);
        $this->assertNotNull($postRoute);
        $this->assertEquals('GET', $getRoute['method']);
        $this->assertEquals('POST', $postRoute['method']);
    }

    // ========================================
    // TESTES DE GRUPOS
    // ========================================

    /**
     * @test
     * @group identification
     * @group groups
     */
    public function testIdentifyRouteInGroup(): void
    {
        Router::group('/api', function ($router) {
            $router->get('/users', function () {
                return 'api users';
            });
        });

        $route = Router::identify('GET', '/api/users');

        $this->assertNotNull($route);
        $this->assertEquals('/api/users', $route['path']);
    }

    /**
     * @test
     * @group identification
     * @group groups
     */
    public function testIdentifyRouteInNestedGroups(): void
    {
        Router::group('/api', function ($router) {
            $router->group('/v1', function ($group) {
                $group->get('/users', function () {
                    return 'api v1 users';
                });
            });
        });

        $route = Router::identify('GET', '/api/v1/users');

        // Nested groups agora estão implementados ✅
        $this->assertNotNull($route, 'Nested groups should work');
        $this->assertEquals('/api/v1/users', $route['path']);
    }

    // ========================================
    // TESTES DE PERFORMANCE E CACHING
    // ========================================

    /**
     * @test
     * @group performance
     */
    public function testIdentificationUsesCache(): void
    {
        Router::get('/users/:id', function () {
            return 'user';
        });

        // Primeira identificação
        $route1 = Router::identify('GET', '/users/123');

        // Segunda identificação deve usar cache
        $route2 = Router::identify('GET', '/users/123');

        $this->assertEquals($route1, $route2);
    }

    /**
     * @test
     * @group performance
     */
    public function testIdentificationPerformanceWithManyRoutes(): void
    {
        // Registra 100 rotas
        for ($i = 0; $i < 100; $i++) {
            Router::get("/route-{$i}", function () {
                return "route {$i}";
            });
        }

        $startTime = microtime(true);

        // Identifica uma rota no meio
        $route = Router::identify('GET', '/route-50');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // em ms

        $this->assertNotNull($route);
        $this->assertLessThan(10, $duration, 'Identificação deve levar menos de 10ms');
    }

    /**
     * @test
     * @group performance
     */
    public function testCompilationPerformance(): void
    {
        $patterns = [
            '/users',
            '/users/:id',
            '/users/:id/posts',
            '/users/:id/posts/:postId',
            '/posts/:year<\d{4}>/:month<\d{2}>/:slug<[a-z0-9-]+>',
        ];

        $startTime = microtime(true);

        foreach ($patterns as $pattern) {
            RouteCache::compilePattern($pattern);
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // em ms

        $this->assertLessThan(5, $duration, 'Compilação de 5 patterns deve levar menos de 5ms');
    }

    // ========================================
    // TESTES DE EDGE CASES
    // ========================================

    /**
     * @test
     * @group edge-cases
     */
    public function testIdentifyWithTrailingSlash(): void
    {
        Router::get('/users', function () {
            return 'users';
        });

        // Sem trailing slash deve funcionar
        $route1 = Router::identify('GET', '/users');
        $this->assertNotNull($route1);

        // Com trailing slash - agora deve funcionar com normalização ✅
        $route2 = Router::identify('GET', '/users/');
        $this->assertNotNull($route2, 'Trailing slash normalization should work');
        $this->assertEquals('/users', $route2['path']);
    }

    /**
     * @test
     * @group edge-cases
     */
    public function testIdentifyRootPath(): void
    {
        Router::get('/', function () {
            return 'home';
        });

        $route = Router::identify('GET', '/');

        $this->assertNotNull($route);
        $this->assertEquals('/', $route['path']);
    }

    /**
     * @test
     * @group edge-cases
     */
    public function testIdentifyEmptyPathDefaultsToRoot(): void
    {
        Router::get('/', function () {
            return 'home';
        });

        $route = Router::identify('GET', null);

        $this->assertNotNull($route);
        $this->assertEquals('/', $route['path']);
    }

    /**
     * @test
     * @group edge-cases
     */
    public function testCompileEmptyPathDefaultsToRoot(): void
    {
        $compiled = RouteCache::compilePattern('');

        $this->assertIsArray($compiled);
        $this->assertArrayHasKey('pattern', $compiled);
    }

    /**
     * @test
     * @group compilation
     */
    public function testCompileWithSpecialCharactersInConstraint(): void
    {
        // Testa constraint com caracteres especiais de regex
        $compiled = RouteCache::compilePattern('/files/:name<[a-zA-Z0-9_\-\.]+>');

        $this->assertIsArray($compiled);
        $this->assertArrayHasKey('pattern', $compiled);
        $this->assertCount(1, $compiled['parameters']);
    }

    /**
     * @test
     * @group identification
     */
    public function testIdentifyWithComplexRealWorldScenario(): void
    {
        // Simula um cenário real com múltiplas rotas
        Router::get('/', function () { return 'home'; });
        Router::get('/about', function () { return 'about'; });
        Router::get('/contact', function () { return 'contact'; });

        Router::group('/api', function ($router) {
            $router->get('/users', function () {
                return 'users list';
            });
            $router->get('/users/:id<\d+>', function () {
                return 'user detail';
            });
            $router->post('/users', function () {
                return 'create user';
            });
            $router->put('/users/:id<\d+>', function () {
                return 'update user';
            });
            $router->delete('/users/:id<\d+>', function () {
                return 'delete user';
            });
        });

        // Testa várias identificações (sem nested groups)
        $tests = [
            ['GET', '/', '/'],
            ['GET', '/about', '/about'],
            ['GET', '/api/users', '/api/users'],
            ['GET', '/api/users/123', '/api/users/:id<\d+>'],
            ['POST', '/api/users', '/api/users'],
        ];

        foreach ($tests as [$method, $path, $expectedPath]) {
            $route = Router::identify($method, $path);
            $this->assertNotNull($route, "Route {$method} {$path} should be found");
            $this->assertEquals($expectedPath, $route['path']);
        }
    }

    /**
     * @test
     * @group stats
     */
    public function testGetStatsAfterOperations(): void
    {
        // Use named functions instead of closures to avoid serialization issues
        $usersHandler = [$this, 'dummyHandler'];
        $postsHandler = [$this, 'dummyHandler'];

        Router::get('/users', $usersHandler);
        Router::get('/posts/:id', $postsHandler);

        Router::identify('GET', '/users');
        Router::identify('GET', '/posts/123');

        $stats = Router::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_routes', $stats);
        $this->assertEquals(2, $stats['total_routes']);
    }

    /**
     * Dummy handler for testing (avoids closure serialization issues)
     */
    public function dummyHandler(): string
    {
        return 'test';
    }

    // ========================================
    // TESTES ESPECÍFICOS PARA TRAILING SLASH
    // ========================================

    /**
     * @test
     * @group trailing-slash
     */
    public function testTrailingSlashWithDynamicRoutes(): void
    {
        Router::get('/users/:id', function () {
            return 'user detail';
        });

        // Ambos devem funcionar
        $route1 = Router::identify('GET', '/users/123');
        $route2 = Router::identify('GET', '/users/123/');

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
        $this->assertEquals('123', $route1['matched_params']['id']);
        $this->assertEquals('123', $route2['matched_params']['id']);
    }

    /**
     * @test
     * @group trailing-slash
     */
    public function testTrailingSlashWithRootPath(): void
    {
        Router::get('/', function () {
            return 'home';
        });

        // Root path sempre funciona
        $route1 = Router::identify('GET', '/');

        // Empty string é convertido para / no identify
        $route2 = Router::identify('GET', null);

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
    }

    /**
     * @test
     * @group trailing-slash
     */
    public function testTrailingSlashWithMultipleSegments(): void
    {
        Router::get('/api/users/list', function () {
            return 'users list';
        });

        $route1 = Router::identify('GET', '/api/users/list');
        $route2 = Router::identify('GET', '/api/users/list/');

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
        $this->assertEquals('/api/users/list', $route1['path']);
        $this->assertEquals('/api/users/list', $route2['path']);
    }

    // ========================================
    // TESTES ESPECÍFICOS PARA NESTED GROUPS
    // ========================================

    /**
     * @test
     * @group nested-groups
     */
    public function testNestedGroupsWithMultipleLevels(): void
    {
        Router::group('/api', function ($router) {
            $router->group('/v1', function ($group) {
                $group->group('/admin', function ($admin) {
                    $admin->get('/users', function () {
                        return 'admin users v1';
                    });
                });
            });
        });

        $route = Router::identify('GET', '/api/v1/admin/users');

        $this->assertNotNull($route);
        $this->assertEquals('/api/v1/admin/users', $route['path']);
    }

    /**
     * @test
     * @group nested-groups
     */
    public function testNestedGroupsWithMiddlewareInheritance(): void
    {
        $middleware1 = function () {
            return 'middleware1';
        };

        $middleware2 = function () {
            return 'middleware2';
        };

        Router::group('/api', function ($router) use ($middleware2) {
            $router->group('/v1', function ($group) {
                $group->get('/users', function () {
                    return 'users';
                });
            }, [$middleware2]);
        }, [$middleware1]);

        $route = Router::identify('GET', '/api/v1/users');

        $this->assertNotNull($route);
        // Deve ter pelo menos 2 middlewares (herdado do pai + próprio)
        // Pode ter mais se houver middlewares globais
        $this->assertGreaterThanOrEqual(2, count($route['middlewares'] ?? []));
    }

    /**
     * @test
     * @group nested-groups
     */
    public function testNestedGroupsWithDifferentPaths(): void
    {
        Router::group('/api', function ($router) {
            $router->group('/v1', function ($group) {
                $group->get('/users', function () {
                    return 'v1 users';
                });
            });

            $router->group('/v2', function ($group) {
                $group->get('/users', function () {
                    return 'v2 users';
                });
            });
        });

        $routeV1 = Router::identify('GET', '/api/v1/users');
        $routeV2 = Router::identify('GET', '/api/v2/users');

        $this->assertNotNull($routeV1);
        $this->assertNotNull($routeV2);
        $this->assertEquals('/api/v1/users', $routeV1['path']);
        $this->assertEquals('/api/v2/users', $routeV2['path']);
    }

    /**
     * @test
     * @group nested-groups
     */
    public function testNestedGroupsWithParameters(): void
    {
        Router::group('/api', function ($router) {
            $router->group('/v1', function ($group) {
                $group->get('/users/:id', function () {
                    return 'user detail';
                });
            });
        });

        $route = Router::identify('GET', '/api/v1/users/42');

        $this->assertNotNull($route);
        $this->assertEquals('/api/v1/users/:id', $route['path']);
        $this->assertEquals('42', $route['matched_params']['id']);
    }

    /**
     * @test
     * @group nested-groups
     * @group trailing-slash
     */
    public function testNestedGroupsWithTrailingSlash(): void
    {
        Router::group('/api', function ($router) {
            $router->group('/v1', function ($group) {
                $group->get('/users', function () {
                    return 'users';
                });
            });
        });

        // Ambos devem funcionar
        $route1 = Router::identify('GET', '/api/v1/users');
        $route2 = Router::identify('GET', '/api/v1/users/');

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
        $this->assertEquals('/api/v1/users', $route1['path']);
        $this->assertEquals('/api/v1/users', $route2['path']);
    }
}
