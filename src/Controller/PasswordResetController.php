<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MailService;
use App\Service\PasswordPolicy;
use App\Service\PasswordResetService;
use App\Service\UserService;
use App\Service\SessionService;
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
    public function request(Request $request, Response $response): Response
    {
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

        $mailer = $request->getAttribute('mailService');
        if (!$mailer instanceof MailService) {
            $twig = Twig::fromRequest($request)->getEnvironment();
            $audit = $request->getAttribute('auditLogger');
            $logger = $audit instanceof AuditLogger ? $audit : null;
            $mailer = new MailService($twig, $logger);
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
    public function confirm(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $token = (string) ($data['token'] ?? '');
        $pass = (string) ($data['password'] ?? '');
        if ($token === '' || $pass === '' || !$this->policy->validate($pass)) {
            return $response->withStatus(400);
        }

        $userId = $this->resets->consumeToken($token);
        if ($userId === null) {
            return $response->withStatus(400);
        }

        $this->users->updatePassword($userId, $pass);
        $this->sessions->invalidateUserSessions($userId);

        return $response->withStatus(204);
    }
}
