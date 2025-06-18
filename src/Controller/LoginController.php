<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConfigService;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class LoginController
{
    public function show(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        $pdo = Database::connectFromEnv();
        $config = (new ConfigService($pdo))->getConfig();

        $user = $config['adminUser'] ?? 'admin';
        $pass = $config['adminPass'] ?? 'password';

        if (($data['username'] ?? '') === $user && ($data['password'] ?? '') === $pass) {
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
