<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MailService;
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
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $view->render($response, 'marketing/calserver.twig', [
            'mailConfigured' => MailService::isConfigured(),
            'csrf_token' => $csrf,
        ]);
    }
}
