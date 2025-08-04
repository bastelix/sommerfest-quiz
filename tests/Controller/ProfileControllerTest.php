<?php

declare(strict_types=1);

namespace Tests\Controller;

use Slim\Psr7\Uri;
use Slim\Psr7\Factory\StreamFactory;
use Tests\TestCase;
use PDO;

class ProfileControllerTest extends TestCase
{
    private function setupDb(): string
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testUpdateProfileMainDomain(): void
    {
        $db = $this->setupDb();
        $profilePath = dirname(__DIR__, 2) . '/data/profile.json';
        $backup = file_get_contents($profilePath);
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $request = $this->createRequest('POST', '/admin/profile', ['HTTP_CONTENT_TYPE' => 'application/json']);
        $stream = (new StreamFactory())->createStream(json_encode(['plan' => 'Pro']));
        $request = $request->withBody($stream)
            ->withUri(new Uri('http', 'example.com', 80, '/admin/profile'));
        $response = $app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());
        $data = json_decode(file_get_contents($profilePath), true);
        $this->assertSame('Pro', $data['plan']);
        file_put_contents($profilePath, $backup);
        session_destroy();
        unlink($db);
        putenv('POSTGRES_DSN');
        putenv('POSTGRES_USER');
        putenv('POSTGRES_PASSWORD');
        unset($_ENV['POSTGRES_DSN'], $_ENV['POSTGRES_USER'], $_ENV['POSTGRES_PASSWORD']);
        putenv('MAIN_DOMAIN');
        unset($_ENV['MAIN_DOMAIN']);
    }

}
