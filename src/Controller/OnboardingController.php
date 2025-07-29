<?php

declare(strict_types=1);

namespace App\Controller;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\UserService;
use App\Infrastructure\Database;

/**
 * Display the onboarding wizard for creating a new tenant.
 */
class OnboardingController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $request->getUri()->getHost();

        $serviceUser = getenv('SERVICE_USER') ?: '';
        $servicePass = getenv('SERVICE_PASS') ?: '';
        if ($serviceUser !== '' && $servicePass !== '' && !isset($_SESSION['user'])) {
            $pdo = Database::connectFromEnv();
            $service = new UserService($pdo);
            $record = $service->getByUsername($serviceUser);
            if ($record !== null && (bool)$record['active']) {
                if (password_verify($servicePass, (string)$record['password'])) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user'] = [
                        'id' => $record['id'],
                        'username' => $record['username'],
                        'role' => $record['role'],
                    ];
                }
            }
        }

        $loggedIn = isset($_SESSION['user']);

        $reloadToken = getenv('NGINX_RELOAD_TOKEN') ?: '';

        return $view->render(
            $response,
            'onboarding.twig',
            [
                'main_domain' => $mainDomain,
                'logged_in' => $loggedIn,
                'reload_token' => $reloadToken,
            ]
        );
    }
}
