<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Store the player name in the session.
 */
class PlayerSessionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Response $response): Response {
        if ($request->getMethod() === 'DELETE') {
            unset($_SESSION['player_name']);

            return $response->withStatus(204);
        }

        $data = json_decode((string) $request->getBody(), true);
        $name = is_array($data) ? ($data['name'] ?? '') : '';
        if (!is_string($name) || trim($name) === '') {
            return $response->withStatus(400);
        }
        $_SESSION['player_name'] = $name;
        return $response->withStatus(204);
    }
}
