<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\PasswordPolicy;
use App\Service\PasswordResetService;
use App\Service\UserService;
use App\Service\SessionService;
use App\Service\SettingsService;
use App\Service\NamespaceResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Service\AuditLogger;

/**
 * Handle password reset requests and confirmations.
 */
class PasswordResetController
{
    private UserService $users;
    private PasswordResetService $resets;
    private PasswordPolicy $policy;
    private SessionService $sessions;

    public function __construct(
        UserService $users,
        PasswordResetService $resets,
        PasswordPolicy $policy,
        SessionService $sessions
    ) {
        $this->users = $users;
        $this->resets = $resets;
        $this->policy = $policy;
        $this->sessions = $sessions;
    }

    /**
     * Accept username or email, generate token and send reset link.
     */
    public function request(Request $request, Response $response): Response {
        $isJson = $request->getHeaderLine('Content-Type') === 'application/json';
        $data = $request->getParsedBody();
        if ($isJson) {
            $data = json_decode((string) $request->getBody(), true);
        }

        if (!is_array($data)) {
            if ($isJson) {
                return $response->withStatus(400);
            }
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(400), 'password_request.twig', ['error' => true]);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($username === '' && $email === '') {
            if ($isJson) {
                return $response->withStatus(400);
            }
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(400), 'password_request.twig', ['error' => true]);
        }

        $user = $username !== ''
            ? $this->users->getByUsername($username)
            : $this->users->getByEmail($email);

        if ($user === null || $user['email'] === null) {
            if ($isJson) {
                return $response->withStatus(404);
            }
            $view = Twig::fromRequest($request);
            return $view->render($response, 'password_request.twig', ['error' => true]);
        }

        $token = $this->resets->createToken((int) $user['id']);
        $uri = $request->getUri()
            ->withPath('/password/reset')
            ->withQuery('token=' . urlencode($token));

        $namespace = (new NamespaceResolver())->resolve($request)->getNamespace();
        $manager = $request->getAttribute('mailProviderManager');
        if (!$manager instanceof MailProviderManager) {
            $pdo = Database::connectFromEnv();
            $manager = new MailProviderManager(new SettingsService($pdo), [], null, $namespace);
        }

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            if (!$manager->isConfigured()) {
                return $response->withStatus(503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $audit = $request->getAttribute('auditLogger');
            $logger = $audit instanceof AuditLogger ? $audit : null;
            $mailer = new MailService($twig, $manager, $logger);
        }
        $mailer->sendPasswordReset((string) $user['email'], (string) $uri);

        if ($isJson) {
            return $response->withStatus(204);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'password_request.twig', ['success' => true]);
    }

    /**
     * Verify token and set new password.
     */
    public function confirm(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $csrf = (string) ($_POST['csrf_token'] ?? '');
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($csrf === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrf)) {
            return $response->withStatus(403);
        }

        $token = (string) ($data['token'] ?? '');
        $pass = (string) ($data['password'] ?? '');
        $repeat = trim((string) ($data['password_repeat'] ?? ''));
        $next = (string) ($data['next'] ?? '');
        $missingRepeat = $repeat === '';
        $mismatch = $repeat !== $pass;
        if (
            $token === ''
            || $pass === ''
            || $missingRepeat
            || $mismatch
            || !$this->policy->validate($pass)
        ) {
            $view = Twig::fromRequest($request);
            $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
            $_SESSION['csrf_token'] = $csrf;

            $params = [
                'token'      => $token,
                'csrf_token' => $csrf,
                'next'       => $next,
            ];
            if ($missingRepeat || $mismatch) {
                $params['mismatch'] = true;
            } else {
                $params['error'] = true;
            }

            return $view->render(
                $response->withStatus(400),
                'password_confirm.twig',
                $params
            );
        }

        $userId = $this->resets->consumeToken($token);
        if ($userId === null) {
            return $response->withStatus(400);
        }

        $this->users->updatePassword($userId, $pass);
        $this->sessions->invalidateUserSessions($userId);
        unset($_SESSION['csrf_token']);

        if ($next !== '' && str_starts_with($next, '/')) {
            $user = $this->users->getById($userId);
            if ($user !== null) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                ];
                $sid = session_id();
                $this->sessions->persistSession($userId, $sid);
                return $response->withHeader('Location', $next)->withStatus(302);
            }
        }

        return $response
            ->withHeader('Location', '/login?reset=1')
            ->withStatus(302);
    }
}
