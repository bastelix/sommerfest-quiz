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
        $path = dirname(__DIR__, 3) . '/content/landing.html';
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $html = (string) file_get_contents($path);
        $basePath = RouteContext::fromRequest($request)->getBasePath();
        $html = str_replace('{{ basePath }}', $basePath, $html);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;
        $html = str_replace('{{ csrf_token }}', $csrf, $html);

        if (!MailService::isConfigured()) {
            $html = preg_replace(
                '/<form id="contact-form"[\s\S]*?<\/form>/',
                '<p class="uk-text-center">Kontaktformular derzeit nicht verfügbar.</p>',
                $html
            );
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'marketing/landing.twig', [
            'content' => $html,
        ]);
    }
}
