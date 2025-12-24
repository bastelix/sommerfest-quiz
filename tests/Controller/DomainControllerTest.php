<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Service\DomainService;
use Tests\TestCase;

class DomainControllerTest extends TestCase
{
    public function testUpdateAcceptsJsonWithCharset(): void
    {
        putenv('MAIN_DOMAIN=example.com');
        $_ENV['MAIN_DOMAIN'] = 'example.com';

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
            CREATE TABLE domains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                host TEXT NOT NULL,
                normalized_host TEXT NOT NULL UNIQUE,
                namespace TEXT,
                label TEXT,
                is_active INTEGER NOT NULL DEFAULT 1
            );
        SQL);
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');

        $this->setDatabase($pdo);

        $service = new DomainService($pdo);
        $domain = $service->createDomain('example.com', 'Example', null, true);

        $request = $this->createRequest(
            'PATCH',
            '/admin/domains/' . $domain['id'],
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $payload = json_encode([
            'host' => 'example.com',
            'label' => 'Updated label',
            'namespace' => null,
            'is_active' => true,
        ], JSON_THROW_ON_ERROR);

        $request->getBody()->write($payload);
        $request->getBody()->rewind();

        $controller = new \App\Controller\Admin\DomainController($service);
        $response = $controller->update($request, new \Slim\Psr7\Response(), ['id' => (string) $domain['id']]);

        $this->assertSame(200, $response->getStatusCode());

        $updated = $service->getDomainById($domain['id']);
        $this->assertNotNull($updated);
        $this->assertSame('Updated label', $updated['label']);
    }
}
