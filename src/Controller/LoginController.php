<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Roles;
use App\Infrastructure\Database;
use App\Service\SessionService;
use App\Service\UserService;
use App\Service\VersionService;
use App\Support\BasePathHelper;
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
    public function show(Request $request, Response $response): Response {
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
        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        return $view->render($response, 'login.twig', [
            'registration_allowed' => $allowed,
            'reset_success' => $resetSuccess,
            'version' => $version,
            'csrf_token' => $csrf,
        ]);
    }

    /**
     * Verify credentials and start an admin session on success.
     */
    public function login(Request $request, Response $response): Response {
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

            $previousNamespace = $_SESSION['user']['active_namespace'] ?? null;
            $sessionService = new SessionService($pdo);
            $activeNamespace = $sessionService->resolveActiveNamespace(
                $record['namespaces'] ?? [],
                is_string($previousNamespace) ? $previousNamespace : null
            );

            $_SESSION['user'] = [
                'id' => $record['id'],
                'username' => $record['username'],
                'role' => $record['role'],
                'active_namespace' => $activeNamespace,
            ];
            $sessionService->persistSession((int) $record['id'], session_id());
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $mainDomain = (string) getenv('MAIN_DOMAIN');
            // Redirect to the configured main domain if the login request
            // was sent to a different host.
            if ($mainDomain !== '' && strcasecmp($host, $mainDomain) !== 0) {
                $scheme = $request->getUri()->getScheme() ?: 'https';
                return $response
                    ->withHeader('Location', $scheme . '://' . $mainDomain . '/admin')
                    ->withStatus(302);
            }
            $dashboardRoles = [
                Roles::ADMIN,
                Roles::CATALOG_EDITOR,
                Roles::EVENT_MANAGER,
                Roles::ANALYST,
                Roles::TEAM_MANAGER,
            ];
            // Service accounts are excluded: they are for automation and have no dashboard.
            if (in_array($record['role'], $dashboardRoles, true)) {
                $target = '/admin';
            } elseif ($record['role'] === Roles::SERVICE_ACCOUNT) {
                // Service accounts are used for automation and have no UI.
                $target = '/';
            } else {
                $target = '/help';
            }
            $basePath = BasePathHelper::normalize(RouteContext::fromRequest($request)->getBasePath());
            return $response->withHeader('Location', $basePath . $target)->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        $inactive = $record !== null && !(bool) $record['active'];
        $unknown = $record === null;

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        if ($unknown) {
            error_log(sprintf('Unknown user "%s" from %s', $identifier, $ip));
        } elseif ($inactive) {
            $email = (string) ($record['email'] ?? '');
            error_log(sprintf('Inactive user "%s" (%s) from %s', (string) $record['username'], $email, $ip));
        } else {
            $email = (string) ($record['email'] ?? '');
            error_log(sprintf('Invalid password for "%s" (%s) from %s', (string) $record['username'], $email, $ip));
        }

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
