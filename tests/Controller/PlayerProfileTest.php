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
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/json', $res->getHeaderLine('Content-Type'));
        $resBody = json_decode((string) $res->getBody(), true);
        $this->assertIsArray($resBody);
        $this->assertSame('uid1', $resBody['player_uid']);

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

    public function testApiPlayersFallsBackToActiveEventWhenMissing(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev-missing','ev-missing','Test Missing')"
        );
        $pdo->exec('DELETE FROM active_event');
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev-missing')");

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'player_name' => 'Dana',
            'player_uid' => 'uid-missing',
        ]);

        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $resBody = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($resBody);
        $this->assertSame('uid-missing', $resBody['player_uid']);

        $stored = $pdo->query(
            "SELECT player_name FROM players WHERE event_uid='ev-missing' AND player_uid='uid-missing'"
        )?->fetchColumn();
        $this->assertSame('Dana', $stored);
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

    public function testApiPlayersRejectsDuplicateNames(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev4','ev4','Test')"
        );
        $pdo->exec(
            "INSERT INTO players(event_uid, player_name, player_uid) VALUES('ev4','Existing','uid-existing')"
        );

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev4',
            'player_name' => 'Existing',
            'player_uid' => 'uid2',
        ]);

        $response = $app->handle($request);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'name_taken']),
            (string) $response->getBody()
        );
    }

    public function testApiPlayersRenamesExistingResults(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev5','ev5','Test')"
        );
        $pdo->prepare(
            "INSERT INTO players(event_uid, player_name, player_uid) VALUES(?,?,?)"
        )->execute(['ev5', 'Old Name', 'uid5']);
        $pdo->prepare(
            "INSERT INTO results(name, catalog, attempt, correct, points, total, max_points, time, event_uid)"
            . " VALUES(?,?,?,?,?,?,?,?,?)"
        )->execute(['Old Name', 'cat-1', 1, 3, 3, 5, 5, 1700000000, 'ev5']);
        $pdo->prepare(
            "INSERT INTO question_results(name, catalog, question_id, attempt, correct, points, event_uid, final_points, efficiency, is_correct, scoring_version)"
            . " VALUES(?,?,?,?,?,?,?,?,?,?,?)"
        )->execute(['Old Name', 'cat-1', 10, 1, 1, 3, 'ev5', 3, 1.0, 1, 1]);

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev5',
            'player_name' => '  New Name  ',
            'player_uid' => 'uid5',
        ]);

        $response = $app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $resBody = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($resBody);
        $this->assertSame('uid5', $resBody['player_uid']);

        $storedName = $pdo->query(
            "SELECT player_name FROM players WHERE event_uid='ev5' AND player_uid='uid5'"
        )?->fetchColumn();
        $this->assertSame('New Name', $storedName);

        $resultName = $pdo->query(
            "SELECT name FROM results WHERE event_uid='ev5'"
        )?->fetchColumn();
        $this->assertSame('New Name', $resultName);

        $questionName = $pdo->query(
            "SELECT name FROM question_results WHERE event_uid='ev5'"
        )?->fetchColumn();
        $this->assertSame('New Name', $questionName);
    }

    public function testApiPlayersGeneratesUidWhenMissing(): void {
        $pdo = $this->getDatabase();
        $pdo->exec(
            "INSERT INTO events(uid, slug, name) VALUES('ev-gen','ev-gen','Test')"
        );

        $app = $this->getAppInstance();

        $request = $this->createRequest('POST', '/api/players');
        $request = $request->withParsedBody([
            'event_uid' => 'ev-gen',
            'player_name' => 'ServerGen',
        ]);

        $response = $app->handle($request);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('player_uid', $body);
        $this->assertNotEmpty($body['player_uid']);
        $this->assertSame(32, strlen($body['player_uid']));

        $stored = $pdo->query(
            "SELECT player_name FROM players WHERE event_uid='ev-gen' AND player_uid='" . $body['player_uid'] . "'"
        )?->fetchColumn();
        $this->assertSame('ServerGen', $stored);
    }

    public function testProfilePageIncludesEventUidWhenConfigEmpty(): void {
        $pdo = $this->getDatabase();
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('ev6','ev6','Test Event')");
        $pdo->exec('DELETE FROM active_event');
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev6')");

        $app = $this->getAppInstance();

        $response = $app->handle($this->createRequest('GET', '/profile'));
        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('"event_uid":"ev6"', $html);
    }
}
