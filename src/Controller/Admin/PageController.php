<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PageController
{
    /**
     * Display the edit form for a static page.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $allowed = ['landing', 'impressum', 'datenschutz', 'faq'];
        if (!in_array($slug, $allowed, true)) {
            return $response->withStatus(404);
        }

        $path = dirname(__DIR__, 3) . '/content/' . $slug . '.html';
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $content = file_get_contents($path) ?: '';
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
        $allowed = ['landing', 'impressum', 'datenschutz', 'faq'];
        if (!in_array($slug, $allowed, true)) {
            return $response->withStatus(404);
        }
        $path = dirname(__DIR__, 3) . '/content/' . $slug . '.html';
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $html = (string)($data['content'] ?? '');
        file_put_contents($path, $html);
        return $response->withStatus(204);
    }
}
