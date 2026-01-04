<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\PageService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PageServiceStartpageTest extends TestCase
{
    private PDO $pdo;

    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            "CREATE UNIQUE INDEX pages_namespace_domain_startpage_idx\n"
            . "ON pages (namespace, COALESCE(startpage_domain, ''))\n"
            . 'WHERE is_startpage = 1'
        );

        $this->service = new PageService($this->pdo);
    }

    public function testResolvePrefersDomainStartpage(): void
    {
        $this->insertPage(1, 'default', 'fallback', true, null, 'de');
        $this->insertPage(2, 'default', 'domain-home', true, 'example.org', 'de');

        $domainStartpage = $this->service->resolveStartpage('default', 'en', 'example.org');
        $this->assertNotNull($domainStartpage);
        $this->assertSame('domain-home', $domainStartpage?->getSlug());
        $this->assertSame('example.org', $domainStartpage?->getStartpageDomain());

        $fallbackStartpage = $this->service->resolveStartpage('default', 'en', 'other.test');
        $this->assertNull($fallbackStartpage);
    }

    public function testMarkAndClearKeepDomainIsolation(): void
    {
        $this->insertPage(1, 'default', 'fallback', true, null, 'de');
        $this->insertPage(2, 'default', 'domain-home', false, null, 'de');

        $this->service->markAsStartpage(2, 'default', 'Example.org');

        $domainStartpage = $this->service->resolveStartpage('default', null, 'example.org');
        $this->assertNotNull($domainStartpage);
        $this->assertSame('domain-home', $domainStartpage?->getSlug());
        $this->assertSame('example.org', $domainStartpage?->getStartpageDomain());

        $namespaceStartpage = $this->service->resolveStartpage('default', null, 'other.host');
        $this->assertNotNull($namespaceStartpage);
        $this->assertSame('fallback', $namespaceStartpage?->getSlug());

        $this->service->clearStartpageForNamespace('default', 'example.org');
        $clearedStartpage = $this->service->resolveStartpage('default', null, 'example.org');
        $this->assertNull($clearedStartpage);
    }

    public function testHostlessStartpageIsPreferredOverDefaultFallback(): void
    {
        $this->insertPage(1, 'default', 'default-home', true, null, 'de');
        $this->insertPage(2, 'calserver', 'namespace-home', true, null, 'de');

        $resolved = $this->service->resolveStartpageSlug('calserver', null, 'calserver.com');

        $this->assertSame('namespace-home', $resolved);
    }

    public function testEmptyDomainStringIsHandledAsNull(): void
    {
        $this->insertPage(1, 'default', 'legacy-empty', true, '', 'de');
        $this->insertPage(2, 'default', 'new-home', false, null, 'de');

        $resolved = $this->service->resolveStartpage('default', null, null);
        $this->assertNotNull($resolved);
        $this->assertSame('legacy-empty', $resolved?->getSlug());

        $this->service->markAsStartpage(2, 'default', null);

        $updated = $this->service->resolveStartpage('default', null, null);
        $this->assertNotNull($updated);
        $this->assertSame('new-home', $updated?->getSlug());
    }

    public function testNamespaceFallbackStillAvailableWithoutDomain(): void
    {
        $this->insertPage(1, 'default', 'fallback', true, null, 'de');

        $resolved = $this->service->resolveStartpage('default', null, null);

        $this->assertNotNull($resolved);
        $this->assertSame('fallback', $resolved?->getSlug());
    }

    private function insertPage(
        int $id,
        string $namespace,
        string $slug,
        bool $isStartpage,
        ?string $domain,
        ?string $language
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages ('
            . 'id, namespace, slug, title, content, type, parent_id, sort_order, status, language, content_source, '
            . 'startpage_domain, is_startpage'
            . ') VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, NULL, ?, NULL, ?, ?)'
        );

        $stmt->execute([
            $id,
            $namespace,
            $slug,
            ucfirst($slug),
            '<p>content</p>',
            $language,
            $domain,
            $isStartpage ? 1 : 0,
        ]);
    }
}
