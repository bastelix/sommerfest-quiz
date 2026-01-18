<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CmsMenuDefinitionService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CmsMenuDefinitionServiceTest extends TestCase
{
    public function testSlotUniquenessRejectsDuplicates(): void
    {
        $pdo = $this->createSchema();
        $service = new CmsMenuDefinitionService($pdo);

        $menu = $service->createMenu('tenant', 'Header', 'de', true);
        $service->createAssignment('tenant', $menu->getId(), 10, 'header', 'de', true);

        $this->expectException(RuntimeException::class);

        $service->createAssignment('tenant', $menu->getId(), 10, 'header', 'de', true);
    }

    private function createSchema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec(
            'CREATE TABLE marketing_menus ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'label TEXT NOT NULL,'
            . "locale TEXT NOT NULL DEFAULT 'de',"
            . 'is_active INTEGER NOT NULL DEFAULT 1,'
            . 'updated_at TEXT'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE marketing_menu_assignments ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'menu_id INTEGER NOT NULL,'
            . 'page_id INTEGER,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'slot TEXT NOT NULL,'
            . "locale TEXT NOT NULL DEFAULT 'de',"
            . 'is_active INTEGER NOT NULL DEFAULT 1,'
            . 'updated_at TEXT'
            . ')'
        );

        $pdo->exec(
            'CREATE UNIQUE INDEX marketing_menu_assignments_page_unique_idx '
            . 'ON marketing_menu_assignments(namespace, page_id, slot, locale)'
        );

        return $pdo;
    }
}
