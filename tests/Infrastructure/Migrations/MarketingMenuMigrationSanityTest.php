<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class MarketingMenuMigrationSanityTest extends TestCase
{
    public function testMigrationKeepsMenuCountsAndTreeStructure(): void
    {
        $pdo = $this->createSchema();
        $this->seedLegacyMenus($pdo);

        $legacyItems = $pdo->query(
            'SELECT id, page_id, parent_id, label, href, locale FROM marketing_page_menu_items'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->migrateLegacyMenus($pdo);

        $newItems = $pdo->query(
            'SELECT id, menu_id, parent_id, label, href, locale FROM marketing_menu_items'
        )->fetchAll(PDO::FETCH_ASSOC);
        $menuAssignments = $pdo->query(
            'SELECT menu_id, page_id, locale FROM marketing_menu_assignments WHERE page_id IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(count($legacyItems), $newItems);

        $legacyTree = $this->buildTreeSignatures($legacyItems, static fn (array $row): string => $row['page_id'] . '|' . $row['locale']);
        $menuMap = [];
        foreach ($menuAssignments as $assignment) {
            $menuMap[(int) $assignment['menu_id']] = (string) $assignment['page_id'] . '|' . (string) $assignment['locale'];
        }
        $newTree = $this->buildTreeSignatures($newItems, static function (array $row) use ($menuMap): string {
            return $menuMap[(int) $row['menu_id']] ?? 'unknown';
        });

        $this->assertSame($legacyTree, $newTree);
    }

    private function createSchema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec(
            'CREATE TABLE pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'title TEXT NOT NULL,'
            . 'is_startpage INTEGER NOT NULL DEFAULT 0'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE marketing_page_menu_items ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'page_id INTEGER NOT NULL,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'parent_id INTEGER,'
            . 'label TEXT NOT NULL,'
            . 'href TEXT NOT NULL,'
            . 'icon TEXT,'
            . "layout TEXT NOT NULL DEFAULT 'link',"
            . 'detail_title TEXT,'
            . 'detail_text TEXT,'
            . 'detail_subline TEXT,'
            . 'position INTEGER NOT NULL DEFAULT 0,'
            . 'is_external INTEGER NOT NULL DEFAULT 0,'
            . "locale TEXT NOT NULL DEFAULT 'de',"
            . 'is_active INTEGER NOT NULL DEFAULT 1,'
            . 'is_startpage INTEGER NOT NULL DEFAULT 0'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE marketing_menus ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'label TEXT NOT NULL,'
            . "locale TEXT NOT NULL DEFAULT 'de',"
            . 'is_active INTEGER NOT NULL DEFAULT 1'
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
            . 'is_active INTEGER NOT NULL DEFAULT 1'
            . ')'
        );

        $pdo->exec(
            'CREATE TABLE marketing_menu_items ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'menu_id INTEGER NOT NULL,'
            . 'parent_id INTEGER,'
            . "namespace TEXT NOT NULL DEFAULT 'default',"
            . 'label TEXT NOT NULL,'
            . 'href TEXT NOT NULL,'
            . 'icon TEXT,'
            . 'position INTEGER NOT NULL DEFAULT 0,'
            . 'is_external INTEGER NOT NULL DEFAULT 0,'
            . "locale TEXT NOT NULL DEFAULT 'de',"
            . 'is_active INTEGER NOT NULL DEFAULT 1,'
            . "layout TEXT NOT NULL DEFAULT 'link',"
            . 'detail_title TEXT,'
            . 'detail_text TEXT,'
            . 'detail_subline TEXT,'
            . 'is_startpage INTEGER NOT NULL DEFAULT 0'
            . ')'
        );

        return $pdo;
    }

    private function seedLegacyMenus(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO pages (id, namespace, title, is_startpage) VALUES (1, 'tenant', 'Landing', 1)");
        $pdo->exec("INSERT INTO pages (id, namespace, title, is_startpage) VALUES (2, 'tenant', 'About', 0)");

        $stmt = $pdo->prepare(
            'INSERT INTO marketing_page_menu_items (page_id, namespace, parent_id, label, href, position, locale) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([1, 'tenant', null, 'Home', '/home', 0, 'de']);
        $parentId = (int) $pdo->lastInsertId();
        $stmt->execute([1, 'tenant', $parentId, 'Child', '/home#child', 1, 'de']);
        $stmt->execute([1, 'tenant', null, 'FAQ', '/faq', 2, 'de']);
        $stmt->execute([2, 'tenant', null, 'About', '/about', 0, 'en']);
    }

    private function migrateLegacyMenus(PDO $pdo): void
    {
        $maps = $pdo->query(
            'SELECT DISTINCT page_id, namespace, locale FROM marketing_page_menu_items'
        )->fetchAll(PDO::FETCH_ASSOC);

        $menuMap = [];
        foreach ($maps as $map) {
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_menus (namespace, label, locale, is_active) VALUES (?, ?, ?, 1)'
            );
            $page = $pdo->query('SELECT title FROM pages WHERE id = ' . (int) $map['page_id'])->fetchColumn();
            $stmt->execute([(string) $map['namespace'], 'Navigation – ' . (string) $page, (string) $map['locale']]);
            $menuId = (int) $pdo->lastInsertId();

            $menuMap[(string) $map['page_id'] . '|' . $map['namespace'] . '|' . $map['locale']] = $menuId;

            $assignmentStmt = $pdo->prepare(
                'INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active) '
                . 'VALUES (?, ?, ?, ?, ?, 1)'
            );
            $assignmentStmt->execute([$menuId, (int) $map['page_id'], (string) $map['namespace'], 'main', (string) $map['locale']]);
        }

        $offset = (int) ($pdo->query('SELECT COALESCE(MAX(id), 0) FROM marketing_menu_items')->fetchColumn() ?: 0);

        $legacyItems = $pdo->query('SELECT * FROM marketing_page_menu_items')->fetchAll(PDO::FETCH_ASSOC);
        $insertItem = $pdo->prepare(
            'INSERT INTO marketing_menu_items (id, menu_id, parent_id, namespace, label, href, icon, position, '
            . 'is_external, locale, is_active, layout, detail_title, detail_text, detail_subline, is_startpage) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NULL, ?, 0, ?, 1, ?, NULL, NULL, NULL, 0)'
        );

        foreach ($legacyItems as $legacy) {
            $menuId = $menuMap[(string) $legacy['page_id'] . '|' . $legacy['namespace'] . '|' . $legacy['locale']];
            $parentId = $legacy['parent_id'] !== null ? (int) $legacy['parent_id'] + $offset : null;
            $insertItem->execute([
                (int) $legacy['id'] + $offset,
                $menuId,
                $parentId,
                (string) $legacy['namespace'],
                (string) $legacy['label'],
                (string) $legacy['href'],
                (int) $legacy['position'],
                (string) $legacy['locale'],
                (string) ($legacy['layout'] ?? 'link'),
            ]);
        }

        $startpages = $pdo->query("SELECT id FROM pages WHERE is_startpage = 1")->fetchAll(PDO::FETCH_COLUMN);
        $footerSlots = ['footer_1', 'footer_2', 'footer_3'];
        foreach ($maps as $map) {
            if (!in_array((int) $map['page_id'], $startpages, true)) {
                continue;
            }
            $menuId = $menuMap[(string) $map['page_id'] . '|' . $map['namespace'] . '|' . $map['locale']];
            foreach ($footerSlots as $slot) {
                $stmt = $pdo->prepare(
                    'INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active) '
                    . 'VALUES (?, NULL, ?, ?, ?, 1)'
                );
                $stmt->execute([$menuId, (string) $map['namespace'], $slot, (string) $map['locale']]);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param callable(array<string, mixed>): string $groupKey
     * @return array<string, array<int, string>>
     */
    private function buildTreeSignatures(array $items, callable $groupKey): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$groupKey($item)][] = $item;
        }

        $result = [];
        foreach ($grouped as $key => $groupItems) {
            $result[$key] = $this->buildPathsForGroup($groupItems);
        }

        ksort($result);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function buildPathsForGroup(array $items): array
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item['id']] = $item;
        }

        $paths = [];
        foreach ($items as $item) {
            $paths[] = $this->buildPathSignature($item, $byId);
        }

        sort($paths);

        return $paths;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $byId
     */
    private function buildPathSignature(array $item, array $byId): string
    {
        $segments = [];
        $current = $item;

        while (true) {
            $segments[] = (string) $current['label'] . '|' . (string) $current['href'];
            $parentId = $current['parent_id'] ?? null;
            if ($parentId === null || !isset($byId[(int) $parentId])) {
                break;
            }
            $current = $byId[(int) $parentId];
        }

        return implode('>', array_reverse($segments));
    }
}
