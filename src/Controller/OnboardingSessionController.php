<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Store onboarding wizard data in the server session.
 */
class OnboardingSessionController
{
    /**
     * Return current onboarding data as JSON.
     */
    public function get(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $data = $_SESSION['onboarding'] ?? [];
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Merge provided data into the onboarding session.
     */
    public function store(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $current = $_SESSION['onboarding'] ?? [];
        $_SESSION['onboarding'] = array_merge($current, $data);
        return $response->withStatus(204);
    }

    /**
     * Clear onboarding data from the session.
     */
    public function clear(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['onboarding']);
        return $response->withStatus(204);
    }
}
