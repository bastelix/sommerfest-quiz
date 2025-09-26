<?php

declare(strict_types=1);

namespace Tests\Controller;

use PDO;
use Slim\Psr7\Factory\StreamFactory;
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

    public function testInvalidSlug(): void
    {
        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

        $response = $app->handle($this->createRequest('GET', '/admin/pages/unknown'));
        $this->assertSame(404, $response->getStatusCode());

        session_destroy();
    }

    public function testCreatePageSuccess(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("DELETE FROM pages WHERE slug = 'neue-seite'");

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $stream = (new StreamFactory())->createStream(json_encode([
            'slug' => 'Neue-Seite',
            'title' => 'Neue Seite',
            'content' => '<p>Hallo Welt</p>',
        ], JSON_THROW_ON_ERROR));

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ])->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(201, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame('neue-seite', $payload['page']['slug'] ?? null);
        $this->assertSame('Neue Seite', $payload['page']['title'] ?? null);

        $row = $pdo->query("SELECT slug, title, content FROM pages WHERE slug = 'neue-seite'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('neue-seite', $row['slug']);
        $this->assertSame('Neue Seite', $row['title']);
        $this->assertSame('<p>Hallo Welt</p>', $row['content']);

        session_destroy();
    }

    public function testCreatePageValidationError(): void
    {
        $pdo = $this->getDatabase();
        $pdo->exec("DELETE FROM pages WHERE slug = 'invalid'");

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $stream = (new StreamFactory())->createStream(json_encode([
            'slug' => 'Invalid Slug',
            'title' => '',
            'content' => '',
        ], JSON_THROW_ON_ERROR));

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ])->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(422, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);

        $count = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE slug = 'invalid'")->fetchColumn();
        $this->assertSame(0, $count);

        session_destroy();
    }

    public function testCreatePageConflict(): void
    {
        $pdo = $this->getDatabase();
        $this->seedPage($pdo, 'konflikt', 'Konflikt', '<p>Alt</p>');

        $app = $this->getAppInstance();
        session_start();
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $_SESSION['csrf_token'] = 'token';

        $stream = (new StreamFactory())->createStream(json_encode([
            'slug' => 'konflikt',
            'title' => 'Neuer Titel',
            'content' => '<p>Neu</p>',
        ], JSON_THROW_ON_ERROR));

        $request = $this->createRequest('POST', '/admin/pages', [
            'HTTP_X_CSRF_TOKEN' => 'token',
        ])->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $app->handle($request);
        $this->assertSame(409, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);

        $row = $pdo->query("SELECT content FROM pages WHERE slug = 'konflikt'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('<p>Alt</p>', $row['content']);

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
