<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

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
        $view = Twig::fromRequest($request);
        return $view->render($response, 'login.twig');
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

        $pdo = Database::connectFromEnv();
        $service = new UserService($pdo);

        $record = $service->getByUsername((string)($data['username'] ?? ''));
        $valid = false;
        if ($record !== null) {
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
        return $view->render($response->withStatus(401), 'login.twig', ['error' => true]);
    }
}
