<?php

declare(strict_types=1);

namespace Tests\Controller;

use DateTimeImmutable;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    public function testProfilePageAndApiPlayers(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev1','ev1','Test')"
        );

        $app = $this->getAppInstance();

        $response = $app->handle($this->createRequest('GET', '/profile'));
        $this->assertSame(200, $response->getStatusCode());

        $consentMoment = new DateTimeImmutable('2024-10-21T12:15:30+00:00');
        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev1',
            'player_name' => ' Alice ',
            'player_uid' => 'uid1',
            'contact_email' => ' ALICE@example.com ',
            'consent_granted_at' => $consentMoment->format(DateTimeImmutable::ATOM),
        ]);
        $res = $app->handle($request);
        $this->assertSame(204, $res->getStatusCode());

        $name = $pdo->query(
            "SELECT player_name FROM players WHERE event_uid='ev1' AND player_uid='uid1'"
        )?->fetchColumn();
        $this->assertSame('Alice', $name);

        $contactEmail = $pdo->query(
            "SELECT contact_email FROM players WHERE event_uid='ev1' AND player_uid='uid1'"
        )?->fetchColumn();
        $this->assertSame('alice@example.com', $contactEmail);

        $consentStored = $pdo->query(
            "SELECT consent_granted_at FROM players WHERE event_uid='ev1' AND player_uid='uid1'"
        )?->fetchColumn();
        $this->assertNotFalse($consentStored);

        $storedMoment = new DateTimeImmutable((string) $consentStored);
        $this->assertSame(
            $consentMoment->setTimezone($storedMoment->getTimezone())->format(DateTimeImmutable::ATOM),
            $storedMoment->format(DateTimeImmutable::ATOM)
        );

        $getReq = $this->createRequest('GET', '/api/players?event_uid=ev1&player_uid=uid1');
        $getRes = $app->handle($getReq);
        $this->assertSame(200, $getRes->getStatusCode());
        $this->assertSame('application/json', $getRes->getHeaderLine('Content-Type'));
        $body = (string) $getRes->getBody();
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'player_name' => 'Alice',
                'contact_email' => 'alice@example.com',
                'consent_granted_at' => $storedMoment->format(DateTimeImmutable::ATOM),
            ]),
            $body
        );
    }

    public function testApiPlayersRejectsEmailWithoutConsent(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev2','ev2','Test')"
        );

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev2',
            'player_name' => 'Bob',
            'player_uid' => 'uid2',
            'contact_email' => 'bob@example.com',
        ]);

        $response = $app->handle($request);
        $this->assertSame(400, $response->getStatusCode());

        $record = $pdo->query(
            "SELECT contact_email FROM players WHERE event_uid='ev2' AND player_uid='uid2'"
        )?->fetchColumn();
        $this->assertFalse($record);
    }

    public function testApiPlayersRejectsInvalidConsentTimestamp(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev3','ev3','Test')"
        );

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev3',
            'player_name' => 'Charlie',
            'player_uid' => 'uid3',
            'contact_email' => 'charlie@example.com',
            'consent_granted_at' => 'not-a-date',
        ]);

        $response = $app->handle($request);
        $this->assertSame(400, $response->getStatusCode());

        $record = $pdo->query(
            "SELECT contact_email FROM players WHERE event_uid='ev3' AND player_uid='uid3'"
        )?->fetchColumn();
        $this->assertFalse($record);
    }
}
