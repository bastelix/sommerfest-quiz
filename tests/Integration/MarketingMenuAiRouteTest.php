<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\Admin\ProjectPagesController;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\MarketingMenuService;
use App\Service\PageService;
use App\Service\RagChat\ChatResponderInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Stubs\StaticChatResponder;

final class MarketingMenuAiRouteTest extends TestCase
{
    public function testRouteOverwritesMenuItems(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $this->insertMenuItem($pdo, $page->getId(), 'Alt', '#alt', 0);

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => '#neu', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new MarketingMenuService($pdo, $pageService, $generator);
        $controller = new ProjectPagesController(
            $pdo,
            $pageService,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $menuService
        );

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/admin/pages/' . $page->getId() . '/menu/ai')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(null)
            ->withAttribute('domainNamespace', $page->getNamespace());
        $body = $request->getBody();
        $body->write(json_encode(['locale' => 'de', 'overwrite' => true]));
        $body->rewind();
        $request = $request->withBody($body);
        $response = new Response();

        $result = $controller->generateMenu($request, $response, ['pageId' => $page->getId()]);

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($payload['items'] ?? null);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('Neu', $payload['items'][0]['label']);

        // ensure existing entry was removed when overwriting
        $requestAppend = $factory->createServerRequest('POST', '/admin/pages/' . $page->getId() . '/menu/ai')
            ->withHeader('Content-Type', 'application/json')
            ->withAttribute('domainNamespace', $page->getNamespace());
        $appendBody = $requestAppend->getBody();
        $appendBody->write(json_encode(['overwrite' => false]));
        $appendBody->rewind();
        $requestAppend = $requestAppend->withBody($appendBody);
        $responseAppend = new Response();
        $resultAppend = $controller->generateMenu($requestAppend, $responseAppend, ['pageId' => $page->getId()]);

        $payloadAppend = json_decode((string) $resultAppend->getBody(), true);
        $labels = array_map(static fn (array $item): string => $item['label'] ?? '', $payloadAppend['items'] ?? []);
        $this->assertContains('Neu', $labels);
    }

    public function testRouteRejectsUnknownAnchors(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Unbekannt', 'href' => '#unbekannt', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new MarketingMenuService($pdo, $pageService, $generator);
        $controller = new ProjectPagesController(
            $pdo,
            $pageService,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $menuService
        );

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/admin/pages/' . $page->getId() . '/menu/ai')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(null)
            ->withAttribute('domainNamespace', $page->getNamespace());
        $response = new Response();

        $result = $controller->generateMenu($request, $response, ['pageId' => $page->getId()]);

        $this->assertSame(422, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true);
        $this->assertSame('ai_invalid_links', $payload['error_code']);
        $this->assertSame([], $payload['items']);
    }

    public function testRouteAutoCorrectsAnchorSlugs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');

        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Neu', 'href' => 'neu', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $menuService = new MarketingMenuService($pdo, $pageService, $generator);
        $controller = new ProjectPagesController(
            $pdo,
            $pageService,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $menuService
        );

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/admin/pages/' . $page->getId() . '/menu/ai')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(null)
            ->withAttribute('domainNamespace', $page->getNamespace());
        $body = $request->getBody();
        $body->write(json_encode(['locale' => 'de', 'overwrite' => true]));
        $body->rewind();
        $request = $request->withBody($body);
        $response = new Response();

        $result = $controller->generateMenu($request, $response, ['pageId' => $page->getId()]);

        $this->assertSame(200, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true);
        $this->assertSame('#neu', $payload['items'][0]['href']);
    }

    public function testTimeoutReturnsEmptyItemsAndGatewayTimeout(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pageService = new PageService($pdo);
        $page = $this->seedPage($pdo, $pageService, 'landing');
        $this->insertMenuItem($pdo, $page->getId(), 'Alt', '#alt', 0);

        $timeoutResponder = new class () implements ChatResponderInterface {
            public function respond(array $messages, array $context): string
            {
                throw new \RuntimeException('Failed to contact chat service: cURL error 28: Operation timed out');
            }
        };

        $generator = new MarketingMenuAiGenerator(null, $timeoutResponder, '{{slug}}');
        $menuService = new MarketingMenuService($pdo, $pageService, $generator);
        $controller = new ProjectPagesController(
            $pdo,
            $pageService,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $menuService
        );

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/admin/pages/' . $page->getId() . '/menu/ai')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(null)
            ->withAttribute('domainNamespace', $page->getNamespace());

        $response = new Response();
        $result = $controller->generateMenu($request, $response, ['pageId' => $page->getId()]);

        $this->assertSame(504, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true);
        $this->assertSame([], $payload['items']);
        $this->assertSame('ai_timeout', $payload['error_code']);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM marketing_page_menu_items WHERE page_id = ?');
        $stmt->execute([$page->getId()]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
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

    private function insertMenuItem(PDO $pdo, int $pageId, string $label, string $href, int $position): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_page_menu_items (page_id, namespace, parent_id, label, href, icon, layout, detail_title, '
            . 'detail_text, detail_subline, position, is_external, locale, is_active, is_startpage) '
            . "VALUES (?, 'default', NULL, ?, ?, NULL, 'link', NULL, NULL, NULL, ?, 0, 'de', 1, 0)"
        );
        $stmt->execute([$pageId, $label, $href, $position]);
    }
}
