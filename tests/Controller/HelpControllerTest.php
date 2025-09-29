<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HelpControllerTest extends TestCase
{
    public function testHelpPage(): void {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvitePlaceholderIsReplaced(): void {
        $dbFile = tempnam(sys_get_temp_dir(), 'db');
        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                header TEXT,
                subheader TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
                adminUser TEXT,
                adminPass TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,start_date,end_date,description) " .
            "VALUES('1','event','Event','2024-01-01T10:00','2024-01-01T12:00','Desc')"
        );
        $pdo->exec(
            'INSERT INTO config(inviteText, event_uid) VALUES(' .
            "'Hallo [Team], willkommen zu [EVENT_NAME] am [EVENT_START] " .
            "bis [EVENT_END] - [EVENT_DESCRIPTION]!', '1')"
        );

        putenv('POSTGRES_DSN=sqlite:' . $dbFile);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $dbFile;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);

        $expected = 'Hallo Team´s, willkommen zu Event am 2024-01-01T10:00 bis 2024-01-01T12:00 - Desc!';
        $this->assertStringContainsString($expected, (string)$response->getBody());

        unlink($dbFile);
    }

    public function testInviteTextIsSanitized(): void {
        $dbFile = tempnam(sys_get_temp_dir(), 'db');
        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE events(' .
            'uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT, start_date TEXT, end_date TEXT, ' .
            'description TEXT, sort_order INTEGER DEFAULT 0' .
            ');'
        );
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO config(inviteText, event_uid) VALUES('<script>alert(1)</script>Hi [TEAM]','1')"
        );

        putenv('POSTGRES_DSN=sqlite:' . $dbFile);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $dbFile;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);

        $body = (string)$response->getBody();
        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringContainsString('alert(1)Hi Team´s', $body);

        unlink($dbFile);
    }
}
