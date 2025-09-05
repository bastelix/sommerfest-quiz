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
        $_SESSION = [];
        session_destroy();

        $domain = $request->getUri()->getHost();
        $domain = getenv('MAIN_DOMAIN') ?: $domain;
        $domain = $domain !== '' ? '.' . ltrim($domain, '.') : '';
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(
            session_name(),
            '',
            time() - 3600,
            '/',
            $domain,
            $secure,
            true
        );

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
