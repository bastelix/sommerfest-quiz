<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class MarketingPageNamespaceFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->getDatabase();
        try {
            $stmt = $pdo->prepare('INSERT INTO pages(slug,title,content) VALUES(?,?,?)');
            $stmt->execute(['namespace-fallback', 'Namespace fallback', '<p>Namespace fallback</p>']);
        } catch (\PDOException $e) {
            // Ignore duplicates when running multiple tests with shared databases.
        }
    }

    public function testMissingSlugInQueryNamespaceDoesNotFallbackToDefault(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/namespace-fallback?namespace=calserver');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMissingSlugInDomainNamespaceDoesNotFallbackToDefault(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/namespace-fallback');
        $request = $request->withAttribute('domainNamespace', 'calserver');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
