<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\AccountService;
use App\Service\EmailConfirmationService;
use App\Service\MailProvider\MailProviderManager;
use App\Service\MailService;
use App\Service\NamespaceResolver;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Handle email-based double opt-in registration for central accounts.
 *
 * Reuses the same EmailConfirmationService and MailService as the
 * onboarding flow but creates an account instead of a tenant.
 */
class AccountEmailController
{
    private EmailConfirmationService $confirmService;

    public function __construct(EmailConfirmationService $confirmService)
    {
        $this->confirmService = $confirmService;
    }

    /**
     * POST /auth/email — Accept email, send confirmation link.
     */
    public function request(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $this->json($response, ['error' => 'invalid_request'], 400);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json($response, ['error' => 'invalid_email'], 400);
        }

        // Store email and desired plan/app in session for after confirmation
        $_SESSION['auth_register'] = [
            'email' => $email,
            'plan' => (string) ($data['plan'] ?? $_SESSION['auth_register']['plan'] ?? ''),
            'app' => (string) ($data['app'] ?? $_SESSION['auth_register']['app'] ?? ''),
        ];

        $token = $this->confirmService->createToken($email);
        $base = rtrim(RouteContext::fromRequest($request)->getBasePath(), '/');
        $confirmUri = $request->getUri()
            ->withPath($base . '/auth/email/confirm')
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
                return $this->json($response, ['error' => 'mail_not_configured'], 503);
            }
            $twig = Twig::fromRequest($request)->getEnvironment();
            $mailer = new MailService($twig, $manager);
        }

        $mailer->sendDoubleOptIn($email, (string) $confirmUri, [
            'subject' => 'Konto erstellen – E-Mail bestätigen',
        ]);

        return $response->withStatus(204);
    }

    /**
     * GET /auth/email/confirm — Verify token, create account, redirect.
     */
    public function confirm(Request $request, Response $response): Response
    {
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        if ($token === '') {
            return $response->withStatus(400);
        }

        $email = $this->confirmService->confirmToken($token);
        if ($email === null) {
            $base = RouteContext::fromRequest($request)->getBasePath();

            return $response
                ->withHeader('Location', $base . '/auth/register?error=token_invalid')
                ->withStatus(302);
        }

        // Create or find account
        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $account = $accountService->findByEmail($email);

        if ($account === null) {
            $accountId = $accountService->create($email);
            $account = $accountService->findById($accountId);
        }

        if ($account === null || $account['status'] !== 'active') {
            $base = RouteContext::fromRequest($request)->getBasePath();

            return $response
                ->withHeader('Location', $base . '/auth/register?error=account_inactive')
                ->withStatus(302);
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        $_SESSION['account_email'] = $account['email'];

        // Redirect to stored return URL (Stripe checkout) or account page
        $returnUrl = $_SESSION['auth_return_url'] ?? null;
        unset($_SESSION['auth_return_url']);

        $base = RouteContext::fromRequest($request)->getBasePath();
        $target = is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : $base . '/account/subscriptions';

        return $response->withHeader('Location', $target)->withStatus(302);
    }

    /**
     * GET /auth/email/status — Poll confirmation status (for JS frontend).
     */
    public function status(Request $request, Response $response): Response
    {
        $email = (string) ($request->getQueryParams()['email'] ?? '');
        if ($email === '') {
            return $response->withStatus(400);
        }

        return $this->confirmService->isConfirmed($email)
            ? $response->withStatus(204)
            : $response->withStatus(404);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
