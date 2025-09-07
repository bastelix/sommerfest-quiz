<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    public function testRouterServesRobotsTxt(): void
    {
        $originalBase = getenv('BASE_PATH');
        $originalUri = $_SERVER['REQUEST_URI'] ?? null;
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;

        putenv('BASE_PATH=/base');
        $_SERVER['REQUEST_URI'] = '/base/robots.txt';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $result = include __DIR__ . '/../public/router.php';
        $content = ob_get_clean();

        $this->assertTrue($result);
        $this->assertStringContainsString('User-agent: *', $content);

        if ($originalBase === false) {
            putenv('BASE_PATH');
        } else {
            putenv('BASE_PATH=' . $originalBase);
        }

        if ($originalUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $originalUri;
        }

        if ($originalMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }
    }
}
