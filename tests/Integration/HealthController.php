<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Integration;

use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Http\Response;

/**
 * Test controller for integration tests
 */
class HealthController
{
    public function healthCheck(Request $req, Response $res)
    {
        return $res->json(
            [
                'status' => 'ok',
                'timestamp' => time(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'version' => Application::VERSION
            ]
        );
    }

    public function getUserHealth(Request $req, Response $res)
    {
        $userId = $req->param('userId');
        return $res->json(
            [
                'user_id' => $userId,
                'status' => 'healthy',
                'checked_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    public static function staticHealthCheck(Request $req, Response $res)
    {
        return $res->json(
            [
                'status' => 'static_ok',
                'method' => 'static'
            ]
        );
    }
}
