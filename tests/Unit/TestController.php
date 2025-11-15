<?php

declare(strict_types=1);

namespace PivotPHP\Routing\Tests\Unit;

use PivotPHP\Core\Http\Request;
use PivotPHP\Core\Http\Response;

/**
 * Test class for array callable functionality
 */
class TestController
{
    public function index(Request $req, Response $res): string
    {
        return 'controller index';
    }

    public function show(Request $req, Response $res): string
    {
        $id = $req->param('id');
        return "controller show: {$id}";
    }

    public static function staticMethod(Request $req, Response $res): string
    {
        return 'static method';
    }

    public function healthCheck(Request $req, Response $res): array
    {
        return [
            'status' => 'ok',
            'timestamp' => time(),
            'method' => 'healthCheck'
        ];
    }

    public function withParameters(Request $req, Response $res): array
    {
        return [
            'user_id' => $req->param('userId'),
            'post_id' => $req->param('postId')
        ];
    }
}
