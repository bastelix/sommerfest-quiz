<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
}
