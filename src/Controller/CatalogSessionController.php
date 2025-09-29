<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Store the selected catalog slug in the session.
 */
class CatalogSessionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Response $response): Response {
        $data = json_decode((string) $request->getBody(), true);
        $slug = is_array($data) ? ($data['slug'] ?? '') : '';
        $remember = is_array($data) ? (bool)($data['remember'] ?? false) : false;
        if (!is_string($slug) || trim($slug) === '') {
            return $response->withStatus(400);
        }
        if ($remember) {
            $_SESSION['catalog_slug'] = $slug;
        }
        return $response->withStatus(204);
    }
}
