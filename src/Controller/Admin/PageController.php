<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PageService;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PageController
{
    private PageService $pageService;

    /** @var string[]|null */
    private ?array $editableSlugs = null;

    public function __construct(?PageService $pageService = null) {
        $this->pageService = $pageService ?? new PageService();
    }

    /**
     * Display the edit form for a static page.
     */
    public function edit(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        if (!in_array($slug, $this->getEditableSlugs(), true)) {
            return $response->withStatus(404);
        }

        $content = $this->pageService->getByKey(PageService::DEFAULT_NAMESPACE, (string) $slug);
        if ($content === null) {
            return $response->withStatus(404);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/pages/edit.twig', [
            'slug' => $slug,
            'content' => $content,
        ]);
    }

    /**
     * Persist new HTML for a static page.
     */
    public function update(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        if (!in_array($slug, $this->getEditableSlugs(), true)) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $html = (string)($data['content'] ?? '');
        $this->pageService->save(PageService::DEFAULT_NAMESPACE, (string) $slug, $html);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        if (!in_array($slug, $this->getEditableSlugs(), true)) {
            return $response->withStatus(404);
        }

        if ($this->pageService->findByKey(PageService::DEFAULT_NAMESPACE, (string) $slug) === null) {
            return $response->withStatus(404);
        }

        $this->pageService->delete(PageService::DEFAULT_NAMESPACE, (string) $slug);
        $this->editableSlugs = null;

        return $response->withStatus(204);
    }

    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) $request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $slug = isset($data['slug']) ? (string) $data['slug'] : '';
        $title = isset($data['title']) ? (string) $data['title'] : '';
        $content = isset($data['content']) ? (string) $data['content'] : '';

        try {
            $page = $this->pageService->create(PageService::DEFAULT_NAMESPACE, $slug, $title, $content);
        } catch (InvalidArgumentException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(422);
        } catch (LogicException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }

        $response->getBody()->write(json_encode(['page' => $page], JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    /**
     * Determine which page slugs can be edited via the admin area.
     *
     * @return string[]
     */
    private function getEditableSlugs(): array {
        if ($this->editableSlugs !== null) {
            return $this->editableSlugs;
        }

        $slugs = [];
        foreach ($this->pageService->getAll() as $page) {
            if ($page->getNamespace() !== PageService::DEFAULT_NAMESPACE) {
                continue;
            }
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = true;
        }

        $this->editableSlugs = array_keys($slugs);

        return $this->editableSlugs;
    }
}
