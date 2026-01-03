<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CmsPageWikiSettingsService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CmsPageWikiSettingsServiceTest extends TestCase
{
    public function testDefaultsAndUpdatesSettings(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE marketing_page_wiki_settings (
            page_id INTEGER PRIMARY KEY,
            is_active INTEGER NOT NULL DEFAULT 0,
            menu_label TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $service = new CmsPageWikiSettingsService($pdo);

        $settings = $service->getSettingsForPage(42);
        $this->assertFalse($settings->isActive());
        $this->assertNull($settings->getMenuLabel());

        $updated = $service->updateSettings(42, true, 'Dokumentation');
        $this->assertTrue($updated->isActive());
        $this->assertSame('Dokumentation', $updated->getMenuLabel());

        $roundTrip = $service->getSettingsForPage(42);
        $this->assertTrue($roundTrip->isActive());
        $this->assertSame('Dokumentation', $roundTrip->getMenuLabel());
    }
}
