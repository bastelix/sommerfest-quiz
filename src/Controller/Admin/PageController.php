<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PageService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Views\Twig;

class PageController
{
    private PageService $pageService;

    /** @var string[]|null */
    private ?array $editableSlugs = null;

    public function __construct(?PageService $pageService = null)
    {
        $this->pageService = $pageService ?? new PageService();
    }

    /**
     * Display the edit form for a static page.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        if (!in_array($slug, $this->getEditableSlugs(), true)) {
            return $response->withStatus(404);
        }

        $content = $this->pageService->get($slug);
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
    public function update(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        if (!in_array($slug, $this->getEditableSlugs(), true)) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $html = (string)($data['content'] ?? '');
        $this->pageService->save($slug, $html);

        return $response->withStatus(204);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->parseRequestData($request);
        if ($data === null) {
            return $this->json($response, ['errors' => ['body' => 'Ungültige Anfrage.']], 400);
        }

        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $title = trim((string) ($data['title'] ?? ''));
        $contentValue = $data['content'] ?? '';

        $errors = [];

        if ($slug === '') {
            $errors['slug'] = 'Slug darf nicht leer sein.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
            $errors['slug'] = 'Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.';
        } elseif (strlen($slug) > 100) {
            $errors['slug'] = 'Slug darf maximal 100 Zeichen lang sein.';
        }

        if ($title === '') {
            $errors['title'] = 'Titel darf nicht leer sein.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'Titel darf maximal 150 Zeichen lang sein.';
        }

        if (!is_scalar($contentValue) && $contentValue !== null) {
            $errors['content'] = 'Inhalt muss eine Zeichenkette sein.';
        }

        if ($errors !== []) {
            return $this->json($response, ['errors' => $errors], 422);
        }

        $content = (string) $contentValue;

        try {
            $page = $this->pageService->create($slug, $title, $content);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['errors' => ['general' => $e->getMessage()]], 400);
        } catch (RuntimeException $e) {
            $status = stripos($e->getMessage(), 'existiert bereits') !== false ? 409 : 500;
            return $this->json($response, ['error' => $e->getMessage()], $status);
        }

        if ($this->editableSlugs !== null) {
            $slugValue = $page->getSlug();
            if (!in_array($slugValue, $this->editableSlugs, true)) {
                $this->editableSlugs[] = $slugValue;
            }
        }

        return $this->json($response, ['page' => $page], 201);
    }

    /**
     * Determine which page slugs can be edited via the admin area.
     *
     * @return string[]
     */
    private function getEditableSlugs(): array
    {
        if ($this->editableSlugs !== null) {
            return $this->editableSlugs;
        }

        $slugs = [];
        foreach ($this->pageService->getAll() as $page) {
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $slugs[$slug] = true;
        }

        $this->editableSlugs = array_keys($slugs);

        return $this->editableSlugs;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function parseRequestData(Request $request): ?array
    {
        $data = $request->getParsedBody();
        if (is_array($data)) {
            return $data;
        }

        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $raw = $body->getContents();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function json(Response $response, array $data, int $status): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = json_encode(['error' => 'Encoding error'], JSON_UNESCAPED_UNICODE) ?: '{"error":"Encoding error"}';
            $status = 500;
        }
        $response->getBody()->write($payload);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
