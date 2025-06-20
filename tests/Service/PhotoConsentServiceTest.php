<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PhotoConsentService;
use PDO;
use Tests\TestCase;

class PhotoConsentServiceTest extends TestCase
{
    public function testAddConsentAppendsEntry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE photo_consents(id INTEGER PRIMARY KEY AUTOINCREMENT, team TEXT NOT NULL, time INTEGER NOT NULL);');
        $svc = new PhotoConsentService($pdo);
        $svc->add('TeamA', 123);
        $svc->add('TeamB', 456);
        $stmt = $pdo->query('SELECT team,time FROM photo_consents ORDER BY id');
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $data);
        $this->assertSame('TeamA', $data[0]['team']);
        $this->assertSame(456, (int)$data[1]['time']);
    }

    public function testGetAllReturnsStoredConsents(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE photo_consents(id INTEGER PRIMARY KEY AUTOINCREMENT, team TEXT NOT NULL, time INTEGER NOT NULL);');
        $svc = new PhotoConsentService($pdo);
        $svc->add('TeamA', 1);
        $svc->add('TeamB', 2);

        $data = $svc->getAll();
        $this->assertCount(2, $data);
        $this->assertSame('TeamA', $data[0]['team']);
        $this->assertSame(2, (int)$data[1]['time']);
    }
}
