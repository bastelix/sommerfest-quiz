<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Domain\Roles;
use Tests\TestCase;

class AdminMediaControllerTest extends TestCase
{
    public function testLandingReferencesReturnedInJson(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec('DELETE FROM page_seo_config');
        $pdo->exec('DELETE FROM pages');

        $content = '<img src="/uploads/existing.png" alt="Existing">'
            . '<source srcset="/uploads/missing.avif 1x">';
        $insertPage = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $insertPage->execute(['landing', 'Landing', $content]);
        $pageId = (int) $pdo->lastInsertId();

        $insertSeo = $pdo->prepare(
            'INSERT INTO page_seo_config (page_id, slug, og_image, favicon_path) VALUES (?, ?, ?, ?)'
        );
        $insertSeo->execute([$pageId, 'landing', '/uploads/seo.png', '/uploads/icon.ico']);

        $uploadsDir = dirname(__DIR__, 2) . '/data/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        file_put_contents($uploadsDir . '/existing.png', 'existing');
        file_put_contents($uploadsDir . '/seo.png', 'seo');
        @unlink($uploadsDir . '/icon.ico');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user'] = ['role' => Roles::ADMIN];

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/admin/media/files?scope=global&landing=landing');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);

        $this->assertArrayHasKey('files', $payload);
        $files = $payload['files'];
        $this->assertIsArray($files);
        $this->assertCount(4, $files);

        $byPath = [];
        foreach ($files as $file) {
            $this->assertArrayHasKey('path', $file);
            $byPath[$file['path']] = $file;
            $this->assertArrayHasKey('landing', $file);
            $this->assertIsArray($file['landing']);
        }

        $this->assertTrue(isset($byPath['/uploads/existing.png']));
        $this->assertFalse($byPath['/uploads/existing.png']['missing']);
        $this->assertTrue(in_array('content:img[src]', $byPath['/uploads/existing.png']['landing']['sources'], true));

        $this->assertTrue(isset($byPath['/uploads/missing.avif']));
        $this->assertTrue($byPath['/uploads/missing.avif']['missing']);
        $this->assertTrue(in_array('content:source[srcset]', $byPath['/uploads/missing.avif']['landing']['sources'], true));

        $this->assertTrue(isset($byPath['/uploads/seo.png']));
        $this->assertFalse($byPath['/uploads/seo.png']['missing']);
        $this->assertTrue(in_array('seo:ogImage', $byPath['/uploads/seo.png']['landing']['sources'], true));

        $this->assertTrue(isset($byPath['/uploads/icon.ico']));
        $this->assertTrue($byPath['/uploads/icon.ico']['missing']);
        $this->assertTrue(in_array('seo:faviconPath', $byPath['/uploads/icon.ico']['landing']['sources'], true));

        $this->assertArrayHasKey('pagination', $payload);
        $this->assertSame(4, $payload['pagination']['total']);

        $this->assertArrayHasKey('landing', $payload);
        $this->assertSame('landing', $payload['landing']['slug']);
        $this->assertSame(4, $payload['landing']['totalReferences']);
        $this->assertSame(2, $payload['landing']['missing']);

        @unlink($uploadsDir . '/existing.png');
        @unlink($uploadsDir . '/seo.png');
    }
}
