<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\Marketing\MarketingMenuAiException;
use App\Service\Marketing\MarketingMenuAiErrorMapper;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\CmsPageMenuService;
use App\Service\PageService;
use App\Service\RagChat\ChatResponderInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\StaticChatResponder;

final class CmsPageMenuAiRouteTest extends TestCase
{
    public function testRouteOverwritesMenuItems(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $menuId = $this->seedMenu($pdo, $page->getNamespace());
        $this->seedMenuAssignment($pdo, $menuId, $page->getId(), $page->getNamespace());
        $this->insertMenuItem($pdo, $menuId, $page->getNamespace(), 'Alt', '#alt', 0);

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => '#neu', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new CmsPageMenuService($pdo, $pageService, null, $generator);

        $items = $menuService->generateMenuFromPage($page, 'de', true);

        $this->assertCount(1, $items);
        $this->assertSame('Neu', $items[0]->getLabel());

        // append mode should add items
        $appended = $menuService->generateMenuFromPage($page, null, false);
        $labels = array_map(static fn ($item): string => $item->getLabel(), $appended);
        $this->assertContains('Neu', $labels);
    }

    public function testUnknownAnchorsFallBackToPageSlug(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $menuId = $this->seedMenu($pdo, $page->getNamespace());
        $this->seedMenuAssignment($pdo, $menuId, $page->getId(), $page->getNamespace());

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Unbekannt', 'href' => '#unbekannt', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new CmsPageMenuService($pdo, $pageService, null, $generator);

        $items = $menuService->generateMenuFromPage($page, null, true);

        $this->assertCount(1, $items);
        $this->assertSame('/landing', $items[0]->getHref());
    }

    public function testRouteAutoCorrectsAnchorSlugs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $menuId = $this->seedMenu($pdo, $page->getNamespace());
        $this->seedMenuAssignment($pdo, $menuId, $page->getId(), $page->getNamespace());

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => 'neu', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new CmsPageMenuService($pdo, $pageService, null, $generator);

        $items = $menuService->generateMenuFromPage($page, 'de', true);

        $this->assertCount(1, $items);
        $this->assertSame('#neu', $items[0]->getHref());
    }

    public function testTimeoutReturnsEmptyItemsAndGatewayTimeout(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $menuId = $this->seedMenu($pdo, $page->getNamespace());
        $this->seedMenuAssignment($pdo, $menuId, $page->getId(), $page->getNamespace());
        $this->insertMenuItem($pdo, $menuId, $page->getNamespace(), 'Alt', '#alt', 0);

        $timeoutResponder = new class () implements ChatResponderInterface {
            public function respond(array $messages, array $context): string
            {
                throw new \RuntimeException('Failed to contact chat service: cURL error 28: Operation timed out');
            }
        };

        $generator = new MarketingMenuAiGenerator(null, $timeoutResponder, '{{slug}}');
        $menuService = new CmsPageMenuService($pdo, $pageService, null, $generator);

        try {
            $menuService->generateMenuFromPage($page, null, true);
            $this->fail('Expected RuntimeException for timeout');
        } catch (\RuntimeException $exception) {
            $mapper = new MarketingMenuAiErrorMapper();
            $mapped = $mapper->map($exception);
            $this->assertSame(504, $mapped['status']);
            $this->assertSame('ai_timeout', $mapped['error_code']);
        }

        // existing item should not have been deleted (transaction rolled back)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM marketing_menu_items WHERE menu_id = ?');
        $stmt->execute([$menuId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testPersistenceFailureReturnsDetailedError(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);
        $pdo->exec('CREATE UNIQUE INDEX marketing_menu_label_unique ON marketing_menu_items(menu_id, label)');

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $menuId = $this->seedMenu($pdo, $page->getNamespace());
        $this->seedMenuAssignment($pdo, $menuId, $page->getId(), $page->getNamespace());
        $this->insertMenuItem($pdo, $menuId, $page->getNamespace(), 'Alt', '#alt', 0);

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Alt', 'href' => '#duplicate', 'layout' => 'link'],
            ],
        ])), '{{slug}}');

        $menuService = new CmsPageMenuService($pdo, $pageService, null, $generator);

        try {
            $menuService->generateMenuFromPage($page, null, false);
            $this->fail('Expected MarketingMenuAiException for persistence failure');
        } catch (MarketingMenuAiException $exception) {
            $this->assertSame('persistence_failed', $exception->getErrorCode());
            $this->assertSame(500, $exception->getStatus());
            $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
        }
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . " namespace TEXT NOT NULL DEFAULT 'default',"
            . ' slug TEXT NOT NULL,'
            . ' title TEXT NOT NULL,'
            . ' content TEXT NOT NULL,'
            . ' type TEXT,'
            . ' parent_id INTEGER,'
            . ' sort_order INTEGER NOT NULL DEFAULT 0,'
            . ' status TEXT,'
            . ' language TEXT,'
            . ' content_source TEXT,'
            . ' startpage_domain TEXT,'
            . ' is_startpage INTEGER NOT NULL DEFAULT 0'
            . ')'
        );

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
            . 'is_startpage INTEGER NOT NULL DEFAULT 0,'
            . 'updated_at TEXT'
            . ')'
        );
    }

    private function seedPage(PDO $pdo, PageService $pageService, string $slug)
    {
        $stmt = $pdo->prepare(
            'INSERT INTO pages (namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source, '
            . 'startpage_domain, is_startpage) VALUES (?, ?, ?, ?, NULL, NULL, 0, NULL, ?, NULL, NULL, 0)'
        );
        $content = '<h1 id="' . $slug . '">' . ucfirst($slug) . '</h1><section id="neu">Neu</section>';
        $stmt->execute(['default', $slug, ucfirst($slug), $content, 'de']);

        return $pageService->findById((int) $pdo->lastInsertId());
    }

    private function seedMenu(PDO $pdo, string $namespace): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO marketing_menus (namespace, label, locale, is_active) VALUES (?, 'Navigation', 'de', 1)"
        );
        $stmt->execute([$namespace]);

        return (int) $pdo->lastInsertId();
    }

    private function seedMenuAssignment(PDO $pdo, int $menuId, int $pageId, string $namespace): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO marketing_menu_assignments (menu_id, page_id, namespace, slot, locale, is_active) "
            . "VALUES (?, ?, ?, 'main', 'de', 1)"
        );
        $stmt->execute([$menuId, $pageId, $namespace]);
    }

    private function insertMenuItem(PDO $pdo, int $menuId, string $namespace, string $label, string $href, int $position): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_menu_items (menu_id, namespace, parent_id, label, href, icon, layout, detail_title, '
            . 'detail_text, detail_subline, position, is_external, locale, is_active, is_startpage) '
            . "VALUES (?, ?, NULL, ?, ?, NULL, 'link', NULL, NULL, NULL, ?, 0, 'de', 1, 0)"
        );
        $stmt->execute([$menuId, $namespace, $label, $href, $position]);
    }
}
