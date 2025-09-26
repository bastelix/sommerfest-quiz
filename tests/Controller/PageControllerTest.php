<?php

declare(strict_types=1);

namespace Tests\Controller;

use PDO;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    /**
     * @dataProvider editableSlugProvider
     */
    public function testEditPageIsAccessible(string $slug, string $title): void
    {
        $pdo = $this->getDatabase();
        $this->seedPage($pdo, $slug, $title, '<p>content</p>');

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $response = $app->handle($this->createRequest('GET', '/admin/pages/' . $slug));
        $this->assertSame(200, $response->getStatusCode());

        session_destroy();
    }

    public function testUpdatePersistsContent(): void
    {
        $pdo = $this->getDatabase();
        $this->seedPage($pdo, 'landing', 'Landing', '<p>old</p>');

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $response = $app->handle($this->createRequest('GET', '/admin/pages/landing'));
        $this->assertSame(200, $response->getStatusCode());

        $request = $this->createRequest('POST', '/admin/pages/landing', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ])->withParsedBody(['content' => '<p>new</p>']);
        $result = $app->handle($request);
        $this->assertSame(204, $result->getStatusCode());

        $content = $pdo->query("SELECT content FROM pages WHERE slug='landing'")->fetchColumn();
        $this->assertSame('<p>new</p>', $content);

        session_destroy();
    }

    public function testCreatePageSuccess(): void
    {
        $pdo = $this->getDatabase();
        $app = $this->getAppInstance();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
            'CONTENT_TYPE' => 'application/json',
        ])->withParsedBody([
            'slug' => 'marketing-neu',
            'title' => 'Marketing Neu',
            'content' => '<p>Start</p>',
        ]);

        $response = $app->handle($request);
        $this->assertSame(201, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('page', $payload);
        $page = $payload['page'];
        $this->assertSame('marketing-neu', $page['slug']);
        $this->assertSame('Marketing Neu', $page['title']);

        $row = $pdo->query("SELECT slug, title, content FROM pages WHERE slug = 'marketing-neu'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('Marketing Neu', $row['title']);
        $this->assertSame('<p>Start</p>', $row['content']);

        session_destroy();
    }

    public function testCreatePageValidationErrors(): void
    {
        $app = $this->getAppInstance();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
            'CONTENT_TYPE' => 'application/json',
        ])->withParsedBody([
            'slug' => '',
            'title' => '',
        ]);

        $response = $app->handle($request);
        $this->assertSame(422, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertArrayHasKey('slug', $payload['errors']);
        $this->assertArrayHasKey('title', $payload['errors']);

        session_destroy();
    }

    public function testCreatePageDuplicateSlug(): void
    {
        $pdo = $this->getDatabase();
        $this->seedPage($pdo, 'landing', 'Landing', '<p>landing</p>');

        $app = $this->getAppInstance();

        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'token',
            'CONTENT_TYPE' => 'application/json',
        ])->withParsedBody([
            'slug' => 'landing',
            'title' => 'Landing Copy',
        ]);

        $response = $app->handle($request);
        $this->assertSame(409, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertStringContainsString('existiert bereits', (string) $payload['error']);

        session_destroy();
    }

    public function testInvalidSlug(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $response = $app->handle($this->createRequest('GET', '/admin/pages/unknown'));
        $this->assertSame(404, $response->getStatusCode());

        session_destroy();
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public function editableSlugProvider(): array
    {
        return [
            ['landing', 'Landing'],
            ['calserver', 'calServer'],
            ['lizenz', 'Lizenz'],
        ];
    }

    private function seedPage(PDO $pdo, string $slug, string $title, string $content): void
    {
        $stmt = $pdo->prepare('DELETE FROM pages WHERE slug = ?');
        $stmt->execute([$slug]);

        $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?, ?, ?)');
        $stmt->execute([$slug, $title, $content]);
    }
}
