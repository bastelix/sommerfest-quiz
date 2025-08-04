<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MailService;
use App\Service\PasswordResetService;
use App\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Handle password reset requests and confirmations.
 */
class PasswordResetController
{
    private UserService $users;
    private PasswordResetService $resets;

    public function __construct(UserService $users, PasswordResetService $resets)
    {
        $this->users = $users;
        $this->resets = $resets;
    }

    /**
     * Accept username or email, generate token and send reset link.
     */
    public function request(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if ($request->getHeaderLine('Content-Type') === 'application/json') {
            $data = json_decode((string) $request->getBody(), true);
        }

        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($username === '' && $email === '') {
            return $response->withStatus(400);
        }

        $user = $username !== ''
            ? $this->users->getByUsername($username)
            : $this->users->getByEmail($email);

        if ($user === null || $user['email'] === null) {
            return $response->withStatus(404);
        }

        $token = $this->resets->createToken((int) $user['id']);
        $uri = $request->getUri()
            ->withPath('/password/reset')
            ->withQuery('token=' . urlencode($token));

        $twig = Twig::fromRequest($request)->getEnvironment();
        $mailer = new MailService($twig);
        $mailer->sendPasswordReset((string) $user['email'], (string) $uri);

        return $response->withStatus(204);
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
        if ($token === '' || $pass === '') {
            return $response->withStatus(400);
        }

        $userId = $this->resets->consumeToken($token);
        if ($userId === null) {
            return $response->withStatus(400);
        }

        $this->users->updatePassword($userId, $pass);

        return $response->withStatus(204);
    }
}
