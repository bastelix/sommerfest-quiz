<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;
use Slim\Psr7\Factory\StreamFactory;

class PlayerProfileTest extends TestCase
{
    public function testProfilePageAndApiPlayers(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('ev1','ev1','Test')");

        $app = $this->getAppInstance();

        $response = $app->handle($this->createRequest('GET', '/profile'));
        $this->assertSame(200, $response->getStatusCode());

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev1',
            'player_name' => 'Alice',
            'player_uid' => 'uid1',
        ]);
        $res = $app->handle($request);
        $this->assertSame(204, $res->getStatusCode());

        $name = $pdo->query("SELECT player_name FROM players WHERE event_uid='ev1' AND player_uid='uid1'")?->fetchColumn();
        $this->assertSame('Alice', $name);

        $getReq = $this->createRequest('GET', '/api/players?event_uid=ev1&player_uid=uid1');
        $getRes = $app->handle($getReq);
        $this->assertSame(200, $getRes->getStatusCode());
        $this->assertSame('application/json', $getRes->getHeaderLine('Content-Type'));
        $body = (string) $getRes->getBody();
        $this->assertSame('{"player_name":"Alice"}', $body);
    }
}
