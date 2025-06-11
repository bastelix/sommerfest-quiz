<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $indexPath = dirname(__DIR__, 2) . '/templates/index.html';
        $response->getBody()->write(file_get_contents($indexPath));
        return $response->withHeader('Content-Type', 'text/html');
    }
}
