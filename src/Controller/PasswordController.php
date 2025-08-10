<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordPolicy;
use App\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Allows changing the current user's password.
 */
class PasswordController
{
    private UserService $service;
    private PasswordPolicy $policy;

    /**
     * Inject user service and password policy.
     */
    public function __construct(UserService $service, PasswordPolicy $policy)
    {
        $this->service = $service;
        $this->policy = $policy;
    }

    /**
     * Update the current user's password using the provided request body.
     */
    public function post(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
            if (!is_array($data)) {
                return $response->withStatus(400);
            }
        } elseif (!is_array($data)) {
            return $response->withStatus(400);
        }

        $pass = $data['password'] ?? '';
        if (!is_string($pass) || $pass === '' || !$this->policy->validate($pass)) {
            return $response->withStatus(400);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id = $_SESSION['user']['id'] ?? null;
        if ($id === null) {
            return $response->withStatus(403);
        }

        $this->service->updatePassword((int)$id, $pass);

        return $response->withStatus(204);
    }
}
