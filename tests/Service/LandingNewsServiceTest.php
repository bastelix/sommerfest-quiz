<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\LandingNewsService;
use PDO;
use PHPUnit\Framework\TestCase;

class LandingNewsServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pagesTableSql = <<<'SQL'
CREATE TABLE pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    namespace TEXT NOT NULL DEFAULT 'default',
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    type TEXT,
    parent_id INTEGER,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status TEXT,
    language TEXT,
    content_source TEXT,
    startpage_domain TEXT,
    is_startpage INTEGER NOT NULL DEFAULT 0
);
SQL;
        $this->pdo->exec($pagesTableSql);

        $landingNewsTableSql = <<<'SQL'
CREATE TABLE landing_news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    published_at TEXT,
    is_published INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
SQL;
        $this->pdo->exec($landingNewsTableSql);
        $stmt = $this->pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $stmt->execute(['landing', 'Landing', '<p>Landing</p>']);
    }

    public function testCreateAndFetchPublishedNews(): void
    {
        $service = new LandingNewsService($this->pdo);
        $entry = $service->create(
            1,
            'update',
            'New Feature',
            '<p>Excerpt</p>',
            '<p>Content</p>',
            null,
            true
        );

        $this->assertSame('update', $entry->getSlug());
        $published = $service->getPublishedForPage(1, 5);
        $this->assertCount(1, $published);
        $this->assertSame('New Feature', $published[0]->getTitle());

        $found = $service->findPublished('landing', 'update');
        $this->assertNotNull($found);
        $this->assertSame('landing', $found->getPageSlug());
    }

    public function testUpdateNewsEntry(): void
    {
        $service = new LandingNewsService($this->pdo);
        $service->create(1, 'roadmap', 'Roadmap', null, '<p>Initial</p>', null, false);
        $updated = $service->update(1, 1, 'roadmap', 'Roadmap 2', '<p>Excerpt</p>', '<p>Updated</p>', null, true);

        $this->assertTrue($updated->isPublished());
        $this->assertSame('Roadmap 2', $updated->getTitle());

        $published = $service->getPublishedForPage(1, 5);
        $this->assertCount(1, $published);
        $this->assertSame('roadmap', $published[0]->getSlug());
    }

    public function testDeleteRemovesEntry(): void
    {
        $service = new LandingNewsService($this->pdo);
        $service->create(1, 'archive', 'Archive', null, '<p>Archived</p>', null, false);
        $service->delete(1);

        $all = $service->getAll();
        $this->assertSame([], $all);
    }
}
