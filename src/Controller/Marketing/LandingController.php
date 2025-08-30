<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Displays the landing page for the marketing site.
 */
class LandingController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $basePath = RouteContext::fromRequest($request)->getBasePath();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        $view = Twig::fromRequest($request);

        return $view->render($response, 'marketing/landing/index.twig', [
            'basePath' => $basePath,
            'csrf_token' => $csrf,
            'mailConfigured' => MailService::isConfigured(),
        ]);
    }
}
