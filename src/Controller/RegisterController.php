<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Service\SettingsService;
use App\Service\UserService;
use App\Support\UsernameBlockedException;
use App\Support\UsernameGuard;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;
use PDO;

/**
 * Handles self-registration of backend users.
 */
class RegisterController
{
    /**
     * Display the registration form.
     */
    public function show(Request $request, Response $response): Response {
        $pdo = \App\Support\RequestDatabase::resolve($request);
        $settings = new SettingsService($pdo);
        $allowed = $settings->get('registration_enabled', '0') === '1';
        $view = Twig::fromRequest($request);
        return $view->render($response, 'register.twig', [ 'allowed' => $allowed ]);
    }

    /**
     * Handle registration form submission.
     */
    public function register(Request $request, Response $response): Response {
        $pdo = \App\Support\RequestDatabase::resolve($request);
        $settings = new SettingsService($pdo);
        if ($settings->get('registration_enabled', '0') !== '1') {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $response->withStatus(400);
        }
        $user = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $pass = (string)($data['password'] ?? '');
        $repeat = (string)($data['password_repeat'] ?? '');
        if ($user === '' || $email === '' || $pass === '' || $pass !== $repeat) {
            $view = Twig::fromRequest($request);
            return $view->render(
                $response->withStatus(400),
                'register.twig',
                [
                    'error' => true,
                    'error_message' => null,
                    'allowed' => true,
                ]
            );
        }

        $guard = UsernameGuard::fromConfigFile(null, $pdo);

        try {
            $guard->assertAllowed($user);
        } catch (UsernameBlockedException $exception) {
            $view = Twig::fromRequest($request);
            return $view->render(
                $response->withStatus(400),
                'register.twig',
                [
                    'error' => true,
                    'error_message' => $exception->getMessage(),
                    'allowed' => true,
                ]
            );
        }

        $service = new UserService($pdo, $guard);
        $service->create($user, $pass, $email, Roles::CATALOG_EDITOR, false);
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'register.twig',
            [
                'success' => true,
                'allowed' => true,
                'error_message' => null,
            ]
        );
    }
}
