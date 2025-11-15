<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Integration;

/**
 * Simple controller for example usage
 */
class ExampleController
{
    public function healthCheck($req, $res)
    {
        return $res->json(
            [
                'status' => 'ok',
                'timestamp' => time(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]
        );
    }

    public function getUserById($req, $res)
    {
        $userId = $req->param('id');
        return $res->json(
            [
                'user_id' => $userId,
                'name' => "User {$userId}",
                'active' => true
            ]
        );
    }

    public static function getApiInfo($req, $res)
    {
        return $res->json(
            [
                'api_version' => '1.0',
                'framework' => 'PivotPHP',
                'version' => Application::VERSION
            ]
        );
    }
}
