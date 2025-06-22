<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
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
        $config = (new ConfigService($pdo))->getConfig();

        $user = $config['adminUser'] ?? 'admin';
        $storedPass = $config['adminPass'] ?? 'password';

        $valid = false;
        if (($data['username'] ?? '') === $user) {
            $pwd = $data['password'] ?? '';
            $info = password_get_info($storedPass);
            if ($info['algo'] !== 0) {
                $valid = password_verify($pwd, $storedPass);
            } else {
                $valid = $pwd === $storedPass;
            }
        }

        if ($valid) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['admin'] = true;
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response->withStatus(401), 'login.twig', ['error' => true]);
    }
}
