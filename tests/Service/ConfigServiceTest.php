<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use PDO;
use Tests\TestCase;

class ConfigServiceTest extends TestCase
{
    public function testReadWriteConfig(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
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
        $service = new ConfigService($pdo);
        $data = ['pageTitle' => 'Demo', 'QRUser' => false, 'QRRemember' => true];

        $service->saveConfig($data);
        $json = $service->getJson();
        $this->assertNotNull($json);
        $cfg = $service->getConfig();
        $this->assertSame('Demo', $cfg['pageTitle']);
        $this->assertFalse($cfg['QRUser']);
        $this->assertTrue($cfg['QRRemember']);
    }

    public function testGetJsonReturnsNullIfFileMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                QRUser INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                pageTitle TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                CheckAnswerButton TEXT,
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
        $service = new ConfigService($pdo);

        $this->assertNull($service->getJson());
        $this->assertNotEmpty($service->getConfig());
    }
}
