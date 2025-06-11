<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $path = dirname(__DIR__, 2) . '/templates/admin.html';
        $response->getBody()->write(file_get_contents($path));
        return $response;
    }
}
