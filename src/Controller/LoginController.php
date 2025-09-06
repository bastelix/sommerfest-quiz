<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Service\SessionService;
use App\Infrastructure\Database;
use App\Service\VersionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;
use PDO;

/**
 * Handles administrator authentication.
 */
class LoginController
{
    /**
     * Display the login form.
     */
    public function show(Request $request, Response $response): Response
    {
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $settings = new \App\Service\SettingsService($pdo);
        $allowed = $settings->get('registration_enabled', '0') === '1';
        $view = Twig::fromRequest($request);
        $query = $request->getQueryParams();
        $resetSuccess = array_key_exists('reset', $query);
        $version = getenv('APP_VERSION');
        if ($version === false || $version === '') {
            $version = (new VersionService())->getCurrentVersion();
        }
        return $view->render($response, 'login.twig', [
            'registration_allowed' => $allowed,
            'reset_success' => $resetSuccess,
            'version' => $version,
        ]);
    }

    /**
     * Verify credentials and start an admin session on success.
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (str_starts_with($request->getHeaderLine('Content-Type'), 'application/json')) {
            $data = json_decode((string) $request->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $response->withStatus(400);
            }
        }

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $userService = new UserService($pdo);

        $identifier = (string) ($data['username'] ?? '');
        $record = $userService->getByUsername($identifier);
        if ($record === null) {
            $record = $userService->getByEmail($identifier);
        }

        $valid = false;
        if ($record !== null && (bool) $record['active']) {
            $pwd = (string) ($data['password'] ?? '');
            $valid = password_verify($pwd, (string) $record['password']);
        }

        if ($valid) {
            if (!session_regenerate_id(true)) {
                error_log('Failed to regenerate session ID');
            }

            $_SESSION['user'] = [
                'id' => $record['id'],
                'username' => $record['username'],
                'role' => $record['role'],
            ];
            $sessionService = new SessionService($pdo);
            $sessionService->persistSession((int) $record['id'], session_id());
            $target = $record['role'] === 'admin' ? '/admin' : '/';
            $basePath = RouteContext::fromRequest($request)->getBasePath();
            return $response->withHeader('Location', $basePath . $target)->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        $inactive = $record !== null && !(bool) $record['active'];
        $unknown = $record === null;

        return $view->render(
            $response->withStatus(401),
            'login.twig',
            [
                'error' => true,
                'inactive' => $inactive,
                'unknown' => $unknown,
            ]
        );
    }
}
