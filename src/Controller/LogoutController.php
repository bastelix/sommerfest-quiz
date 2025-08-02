<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Logs out the current user session.
 */
class LogoutController
{
    /**
     * Destroy the admin session and redirect to login.
     */
    public function __invoke(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
