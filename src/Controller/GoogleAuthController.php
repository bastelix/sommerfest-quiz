<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Roles;
use App\Service\GoogleTokenVerifier;
use App\Service\SessionService;
use App\Service\UserService;
use App\Support\BasePathHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

/**
 * Handle Google Sign-In authentication for login and onboarding.
 */
class GoogleAuthController
{
    /**
     * Authenticate a user via Google ID token (login flow).
     */
    public function login(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_request'], 400);
        }

        $idToken = (string) ($data['credential'] ?? '');
        $clientId = (string) (getenv('GOOGLE_CLIENT_ID') ?: '');
        if ($clientId === '') {
            return $this->json($response, ['error' => 'not_configured'], 503);
        }

        $verifier = new GoogleTokenVerifier($clientId);
        $tokenData = $verifier->verify($idToken);
        if ($tokenData === null) {
            return $this->json($response, ['error' => 'invalid_token'], 401);
        }

        $pdo = \App\Support\RequestDatabase::resolve($request);
        $userService = new UserService($pdo);

        $record = $userService->getByGoogleId($tokenData['sub']);
        if ($record === null) {
            $record = $userService->getByEmail($tokenData['email']);
        }

        if ($record === null) {
            return $this->json($response, ['error' => 'unknown'], 401);
        }

        if (!(bool) $record['active']) {
            return $this->json($response, ['error' => 'inactive'], 401);
        }

        // Link Google ID to existing account on first Google login
        $existingGoogle = $userService->getByGoogleId($tokenData['sub']);
        if ($existingGoogle === null) {
            $userService->setGoogleId((int) $record['id'], $tokenData['sub']);
        }

        // Create session (same logic as LoginController)
        if (!session_regenerate_id(true)) {
            error_log('Failed to regenerate session ID');
        }

        $previousNamespace = $_SESSION['user']['active_namespace'] ?? null;
        $sessionService = new SessionService($pdo);
        $activeNamespace = $sessionService->resolveActiveNamespace(
            $record['namespaces'],
            is_string($previousNamespace) ? $previousNamespace : null
        );

        $_SESSION['user'] = [
            'id' => $record['id'],
            'username' => $record['username'],
            'role' => $record['role'],
            'namespaces' => $record['namespaces'],
            'active_namespace' => $activeNamespace,
        ];
        $sessionService->persistSession((int) $record['id'], session_id());

        $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
        $target = $this->resolveRedirectTarget($record['role'], $basePath);

        return $this->json($response, ['redirect' => $target]);
    }

    /**
     * Verify a Google ID token for the onboarding flow.
     */
    public function onboarding(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_request'], 400);
        }

        $idToken = (string) ($data['credential'] ?? '');
        $clientId = (string) (getenv('GOOGLE_CLIENT_ID') ?: '');
        if ($clientId === '') {
            return $this->json($response, ['error' => 'not_configured'], 503);
        }

        $verifier = new GoogleTokenVerifier($clientId);
        $tokenData = $verifier->verify($idToken);
        if ($tokenData === null) {
            return $this->json($response, ['error' => 'invalid_token'], 401);
        }

        $_SESSION['onboarding']['email'] = $tokenData['email'];
        $_SESSION['onboarding']['verified'] = true;

        return $this->json($response, [
            'email' => $tokenData['email'],
            'verified' => true,
        ]);
    }

    private function resolveRedirectTarget(string $role, string $basePath): string
    {
        $dashboardRoles = [
            Roles::ADMIN,
            Roles::CATALOG_EDITOR,
            Roles::EVENT_MANAGER,
            Roles::ANALYST,
            Roles::TEAM_MANAGER,
            Roles::CUSTOMER,
        ];
        $designRoles = [
            Roles::DESIGNER,
            Roles::REDAKTEUR,
        ];

        if (in_array($role, $dashboardRoles, true)) {
            $target = '/admin';
        } elseif (in_array($role, $designRoles, true)) {
            $target = '/admin/pages/design';
        } elseif ($role === Roles::SERVICE_ACCOUNT) {
            $target = '/';
        } else {
            $target = '/help';
        }

        return $basePath . $target;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
