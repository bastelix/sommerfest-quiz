<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\NamespaceResolver;
use App\Service\PageContentFileRepository;
use App\Service\PageContentLoader;
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
    private NamespaceResolver $namespaceResolver;

    /** @var array<string, string[]> */
    private array $editableSlugs = [];

    public function __construct(?PageService $pageService = null, ?NamespaceResolver $namespaceResolver = null) {
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
    }

    /**
     * Display the edit form for a static page.
     */
    public function edit(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $content = $this->pageService->getByKey($namespace, (string) $slug);
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
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $html = (string)($data['content'] ?? '');
        $this->pageService->save($namespace, (string) $slug, $html);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        $slug = $args['slug'] ?? '';
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        if ($this->pageService->findByKey($namespace, (string) $slug) === null) {
            return $response->withStatus(404);
        }

        $this->pageService->delete($namespace, (string) $slug);
        unset($this->editableSlugs[$namespace]);

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
        $contentSource = null;
        if ($content === '' && PageContentFileRepository::hasFallbackForSlug($slug)) {
            $contentSource = PageContentLoader::SOURCE_FILE;
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();

        try {
            $page = $this->pageService->create($namespace, $slug, $title, $content, $contentSource);
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

    public function copy(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $targetNamespace = (string) ($data['targetNamespace'] ?? $data['target_namespace'] ?? $data['namespace'] ?? '');

        try {
            $result = $this->pageService->copy($namespace, (string) $slug, $targetNamespace);
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

        $this->editableSlugs = [];
        $payload = [
            'page' => $result['page'],
            'copied' => $result['copied'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function move(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $response->withStatus(400);
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if (!in_array($slug, $this->getEditableSlugs($namespace), true)) {
            return $response->withStatus(404);
        }

        $targetNamespace = (string) ($data['targetNamespace'] ?? $data['target_namespace'] ?? $data['namespace'] ?? '');

        try {
            $result = $this->pageService->move($namespace, (string) $slug, $targetNamespace);
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

        $this->editableSlugs = [];
        $payload = [
            'page' => $result['page'],
            'moved' => $result['moved'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Return the full page tree for admin UI use.
     */
    public function tree(Request $request, Response $response): Response {
        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        $tree = array_values(array_filter(
            $this->pageService->getTree(),
            static fn (array $section): bool => ($section['namespace'] ?? '') === $namespace
        ));
        $payload = [
            'tree' => $tree,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Determine which page slugs can be edited via the admin area.
     *
     * @return string[]
     */
    private function getEditableSlugs(string $namespace): array {
        if (isset($this->editableSlugs[$namespace])) {
            return $this->editableSlugs[$namespace];
        }

        $slugs = [];
        foreach ($this->pageService->getAll() as $page) {
            if ($page->getNamespace() !== $namespace) {
                continue;
            }
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = true;
        }

        $this->editableSlugs[$namespace] = array_keys($slugs);

        return $this->editableSlugs[$namespace];
    }

    private function parseRequestData(Request $request): ?array
    {
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
            return null;
        }

        return $data;
    }
}
