<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Displays the calServer marketing preview page.
 */
class CalserverController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'marketing/calserver.twig');
    }
}
