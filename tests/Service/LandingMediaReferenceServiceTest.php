<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Application\Routing\RedirectManager;
use App\Application\Seo\PageSeoConfigService;
use App\Application\Seo\SeoValidator;
use App\Infrastructure\Cache\PageSeoCache;
use App\Infrastructure\Event\EventDispatcher;
use App\Service\ConfigService;
use App\Service\LandingNewsService;
use App\Service\LandingMediaReferenceService;
use App\Service\PageService;
use PDO;
use PHPUnit\Framework\TestCase;

class LandingMediaReferenceServiceTest extends TestCase
{
    public function testCollectAggregatesLandingReferences(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . " namespace TEXT NOT NULL DEFAULT 'default',"
            . ' slug TEXT,'
            . ' title TEXT,'
            . ' content TEXT,'
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
            'CREATE TABLE landing_news ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . 'page_id INTEGER NOT NULL,'
            . 'slug TEXT NOT NULL,'
            . 'title TEXT NOT NULL,'
            . 'excerpt TEXT,'
            . 'content TEXT NOT NULL,'
            . 'published_at TEXT,'
            . 'is_published INTEGER NOT NULL DEFAULT 0,'
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP,'
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE page_seo_config (' .
            'page_id INTEGER PRIMARY KEY, slug TEXT, domain TEXT, meta_title TEXT, meta_description TEXT, ' .
            'canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, og_image TEXT, ' .
            'favicon_path TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, ' .
            'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );
        $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $stmt->execute([
            'landing',
            'Landing',
            '<img src="{{ basePath }}/uploads/landing/hero.webp">' .
            '<img src="/uploads/landing/missing.avif">',
        ]);
        $landingId = (int) $pdo->lastInsertId();
        $stmt->execute(['impressum', 'Impressum', '<img src="/uploads/landing/ignore.webp">']);

        $newsStmt = $pdo->prepare(
            'INSERT INTO landing_news (page_id, slug, title, excerpt, content, published_at, is_published) '
            . 'VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $newsStmt->execute([
            $landingId,
            'release',
            'Release',
            '<p><img src="/uploads/landing/news-excerpt-missing.webp" alt="Excerpt"></p>',
            '<p><img src="/uploads/landing/news.webp" alt="News"></p>',
            '2024-10-10T10:00:00+00:00',
        ]);

        $seoInsert = $pdo->prepare(
            'INSERT INTO page_seo_config (' .
            'page_id, slug, domain, meta_title, meta_description, canonical_url, robots_meta, ' .
            'og_title, og_description, og_image, favicon_path, schema_json, hreflang) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $seoInsert->execute([
            $landingId,
            'landing',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'uploads/landing/og.png',
            'uploads/favicon.ico',
            null,
            null,
        ]);

        $config = new ConfigService($pdo);
        $pageService = new PageService($pdo);
        $seoService = new PageSeoConfigService(
            $pdo,
            new RedirectManager($pdo),
            new SeoValidator(),
            new PageSeoCache(),
            new EventDispatcher()
        );
        $newsService = new LandingNewsService($pdo);
        $service = new LandingMediaReferenceService($pageService, $seoService, $config, $newsService);

        $uploadsDir = $config->getGlobalUploadsDir();
        $landingDir = $uploadsDir . DIRECTORY_SEPARATOR . 'landing';
        $createdLandingDir = false;
        if (!is_dir($landingDir)) {
            mkdir($landingDir, 0775, true);
            $createdLandingDir = true;
        }
        $heroPath = $landingDir . DIRECTORY_SEPARATOR . 'hero.webp';
        file_put_contents($heroPath, 'test');
        $newsPath = $landingDir . DIRECTORY_SEPARATOR . 'news.webp';
        file_put_contents($newsPath, 'test');

        try {
            $result = $service->collect();

            $this->assertSame([
                ['slug' => 'landing', 'title' => 'Landing'],
            ], $result['slugs']);

            $this->assertArrayHasKey('uploads/landing/hero.webp', $result['files']);
            $this->assertArrayHasKey('uploads/landing/missing.avif', $result['files']);
            $this->assertArrayHasKey('uploads/landing/news.webp', $result['files']);
            $this->assertArrayHasKey('uploads/landing/news-excerpt-missing.webp', $result['files']);
            $this->assertArrayHasKey('uploads/landing/og.png', $result['files']);

            $heroReferences = $result['files']['uploads/landing/hero.webp'];
            $this->assertCount(1, $heroReferences);
            $this->assertSame('markup', $heroReferences[0]['type']);

            $missingPaths = array_map(static fn(array $entry): string => $entry['path'], $result['missing']);
            $this->assertContains('uploads/landing/missing.avif', $missingPaths);
            $this->assertContains('uploads/landing/news-excerpt-missing.webp', $missingPaths);
            $this->assertContains('uploads/landing/og.png', $missingPaths);
            $this->assertNotContains('uploads/landing/hero.webp', $missingPaths);
            $this->assertNotContains('uploads/landing/news.webp', $missingPaths);

            $missingEntry = null;
            foreach ($result['missing'] as $entry) {
                if ($entry['path'] === 'uploads/landing/missing.avif') {
                    $missingEntry = $entry;
                    break;
                }
            }
            $this->assertNotNull($missingEntry);
            $this->assertSame('landing', $missingEntry['slug']);
            $this->assertSame('missing', $missingEntry['suggestedName']);
            $this->assertSame('landing', $missingEntry['suggestedFolder']);
        } finally {
            @unlink($heroPath);
            @unlink($newsPath);
            if ($createdLandingDir && is_dir($landingDir)) {
                @rmdir($landingDir);
            }
        }
    }

    public function testNormalizeFilePath(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . " namespace TEXT NOT NULL DEFAULT 'default',"
            . ' slug TEXT,'
            . ' title TEXT,'
            . ' content TEXT,'
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
            'CREATE TABLE landing_news (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER NOT NULL, slug TEXT NOT NULL, title TEXT NOT NULL, ' .
            'excerpt TEXT, content TEXT NOT NULL, published_at TEXT, is_published INTEGER NOT NULL DEFAULT 0, ' .
            'created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );
        $pdo->exec(
            'CREATE TABLE page_seo_config (' .
            'page_id INTEGER PRIMARY KEY, slug TEXT, domain TEXT, meta_title TEXT, ' .
            'meta_description TEXT, canonical_url TEXT, robots_meta TEXT, og_title TEXT, og_description TEXT, ' .
            'og_image TEXT, favicon_path TEXT, schema_json TEXT, hreflang TEXT, created_at TEXT, updated_at TEXT)'
        );
        $config = new ConfigService($pdo);
        $pageService = new PageService($pdo);
        $seoService = new PageSeoConfigService(
            $pdo,
            new RedirectManager($pdo),
            new SeoValidator(),
            new PageSeoCache(),
            new EventDispatcher()
        );
        $newsService = new LandingNewsService($pdo);
        $service = new LandingMediaReferenceService($pageService, $seoService, $config, $newsService);

        $this->assertSame(
            'uploads/landing/hero.webp',
            $service->normalizeFilePath('{{ basePath }}/uploads/landing/hero.webp')
        );
        $this->assertSame(
            'uploads/landing/hero.webp',
            $service->normalizeFilePath('/uploads/landing/hero.webp')
        );
        $this->assertSame(
            'uploads/landing/hero.webp',
            $service->normalizeFilePath('uploads/landing/hero.webp')
        );
        $this->assertNull(
            $service->normalizeFilePath('https://example.com/uploads/landing/hero.webp')
        );
        $this->assertNull($service->normalizeFilePath('/images/hero.webp'));
    }
}
