<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Renders the privacy policy page.
 */
class DatenschutzController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/content/datenschutz.html';
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string) file_get_contents($path));
        return $response->withHeader('Content-Type', 'text/html');
    }
}
