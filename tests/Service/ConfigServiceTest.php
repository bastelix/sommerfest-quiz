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
        $data = ['pageTitle' => 'Demo'];

        $service->saveConfig($data);
        $expected = json_encode(['pageTitle' => 'Demo'], JSON_PRETTY_PRINT);
        $this->assertSame($expected, $service->getJson());
        $this->assertEquals($data, $service->getConfig());
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
        $this->assertEquals([], $service->getConfig());
    }

    public function testSanitizeHtmlRemovesDisallowedTags(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><img src="x"><a href="#">Link</a>';
        $result = ConfigService::sanitizeHtml($html);
        $this->assertSame('<p>Hello</p>Link', $result);
    }

    public function testSanitizeHtmlAllowsConfiguredTags(): void
    {
        $html = '<p><br><strong>Bold</strong><b>B</b><em>E</em><i>I</i><h2>T</h2><h3>T3</h3><h4>T4</h4><h5>T5</h5></p>';
        $result = ConfigService::sanitizeHtml($html);
        $this->assertSame($html, $result);
    }
}
