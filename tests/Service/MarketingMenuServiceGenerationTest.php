<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Page;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\MarketingMenuService;
use App\Service\PageService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\StaticChatResponder;

final class MarketingMenuServiceGenerationTest extends TestCase
{
    private PDO $pdo;

    private PageService $pageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->pageService = new PageService($this->pdo);
    }

    public function testOverwriteReplacesExistingMenu(): void
    {
        $page = $this->seedPage('landing');
        $this->insertMenuItem($page->getId(), 'Alt', '#alt', 0);

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => '#neu', 'layout' => 'link', 'children' => []],
            ],
        ])), '{{slug}}');

        $service = new MarketingMenuService($this->pdo, $this->pageService, $generator);
        $items = $service->generateMenuFromPage($page, 'de', true);

        $this->assertCount(1, $items);
        $this->assertSame('Neu', $items[0]->getLabel());
        $this->assertSame('#neu', $items[0]->getHref());
    }

    public function testAppendKeepsExistingMenu(): void
    {
        $page = $this->seedPage('landing');
        $this->insertMenuItem($page->getId(), 'Alt', '#alt', 0);

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => '#neu', 'layout' => 'link', 'children' => []],
            ],
        ])), '{{slug}}');

        $service = new MarketingMenuService($this->pdo, $this->pageService, $generator);
        $items = $service->generateMenuFromPage($page, 'de', false);

        $this->assertCount(2, $items);
        $labels = array_map(static fn ($item) => $item->getLabel(), $items);
        $this->assertSame(['Alt', 'Neu'], $labels);
        $positions = array_map(static fn ($item) => $item->getPosition(), $items);
        $this->assertSame([0, 1], $positions);
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
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

        $this->pdo->exec(
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
            . 'is_startpage INTEGER NOT NULL DEFAULT 0,'
            . 'updated_at TEXT'
            . ')'
        );
    }

    private function seedPage(string $slug): Page
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages (namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source, '
            . 'startpage_domain, is_startpage) VALUES (?, ?, ?, ?, NULL, NULL, 0, NULL, ?, NULL, NULL, 0)'
        );
        $content = '<h1 id="' . $slug . '">' . ucfirst($slug) . '</h1><section id="neu">Neu</section>';
        $stmt->execute(['default', $slug, ucfirst($slug), $content, 'de']);

        return $this->pageService->findById((int) $this->pdo->lastInsertId());
    }

    private function insertMenuItem(int $pageId, string $label, string $href, int $position): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO marketing_page_menu_items (page_id, namespace, parent_id, label, href, icon, layout, detail_title, '
            . 'detail_text, detail_subline, position, is_external, locale, is_active, is_startpage) '
            . "VALUES (?, 'default', NULL, ?, ?, NULL, 'link', NULL, NULL, NULL, ?, 0, 'de', 1, 0)"
        );
        $stmt->execute([$pageId, $label, $href, $position]);
    }
}
