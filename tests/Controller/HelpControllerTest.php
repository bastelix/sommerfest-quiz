<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HelpControllerTest extends TestCase
{
    public function testHelpPage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvitePlaceholderIsReplaced(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'db');
        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(displayErrorDetails INTEGER, QRUser INTEGER, logoPath TEXT, pageTitle TEXT, header TEXT, subheader TEXT, backgroundColor TEXT, buttonColor TEXT, CheckAnswerButton TEXT, adminUser TEXT, adminPass TEXT, QRRestrict INTEGER, competitionMode INTEGER, teamResults INTEGER, photoUpload INTEGER, puzzleWordEnabled INTEGER, puzzleWord TEXT, puzzleFeedback TEXT, inviteText TEXT);');
        $pdo->exec("INSERT INTO config(inviteText) VALUES('Hallo [Team]!');");

        putenv('POSTGRES_DSN=sqlite:' . $dbFile);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $dbFile;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/help');
        $response = $app->handle($request);

        $this->assertStringContainsString('Hallo Team!', (string)$response->getBody());

        unlink($dbFile);
    }
}
