<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use Tests\TestCase;
use Slim\Psr7\Factory\StreamFactory;

class EventConfigControllerTest extends TestCase
{
    public function testPatchUpdatesConfiguration(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO events(uid,name,start_date,end_date,description,published,sort_order) VALUES('e1','Event','2024-01-01T00:00','2024-01-02T00:00',NULL,0,0)");

        $app = $this->getAppInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['id' => 1, 'role' => Roles::EVENT_MANAGER];

        $request = $this->createRequest('PATCH', '/admin/event/e1', [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $stream = (new StreamFactory())->createStream(json_encode([
            'puzzleWordEnabled' => true,
            'puzzleWord' => 'Test',
            'puzzleFeedback' => 'Gut'
        ]));
        $request = $request->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame(true, $data['config']['puzzleWordEnabled'] ?? null);
        $this->assertSame('Test', $data['config']['puzzleWord'] ?? null);
        $this->assertSame('Gut', $data['config']['puzzleFeedback'] ?? null);
        @session_destroy();
    }
}
