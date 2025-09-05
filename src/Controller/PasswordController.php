<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordPolicy;
use App\Service\UserService;
use App\Service\AuditLogger;
use App\Service\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Allows changing the current user's password.
 */
class PasswordController
{
    private UserService $service;
    private PasswordPolicy $policy;
    private AuditLogger $audit;
    private SessionService $sessions;

    /**
     * Inject user service and password policy.
     */
    public function __construct(
        UserService $service,
        PasswordPolicy $policy,
        AuditLogger $audit,
        SessionService $sessions
    ) {
        $this->service = $service;
        $this->policy = $policy;
        $this->audit = $audit;
        $this->sessions = $sessions;
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

        $id = $_SESSION['user']['id'] ?? null;
        if ($id === null) {
            return $response->withStatus(403);
        }

        $this->service->updatePassword((int)$id, $pass);
        $this->sessions->invalidateUserSessions((int)$id);

        $this->audit->log('password_change', ['userId' => $id]);

        return $response->withStatus(204);
    }
}
