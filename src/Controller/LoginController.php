<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
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
        return $view->render($response, 'login.twig', [
            'registration_allowed' => $allowed,
            'reset_success' => $resetSuccess,
        ]);
    }

    /**
     * Verify credentials and start an admin session on success.
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof PDO) {
            $pdo = Database::connectFromEnv();
        }
        $service = new UserService($pdo);

        $record = $service->getByUsername((string)($data['username'] ?? ''));
        $valid = false;
        if ($record !== null && (bool)$record['active']) {
            $pwd = (string)($data['password'] ?? '');
            $valid = password_verify($pwd, (string)$record['password']);
        }

        if ($valid) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user'] = [
                'id' => $record['id'],
                'username' => $record['username'],
                'role' => $record['role'],
            ];
            $target = $record['role'] === 'admin' ? '/admin' : '/';
            return $response->withHeader('Location', $target)->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        $inactive = $record !== null && !(bool)$record['active'];
        return $view->render(
            $response->withStatus(401),
            'login.twig',
            ['error' => true, 'inactive' => $inactive]
        );
    }
}
