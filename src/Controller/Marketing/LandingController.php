<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Displays the landing page for the marketing site.
 */
class LandingController
{
    /**
     * Render the landing page.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'marketing/landing.twig');
    }
}
