<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\AccountService;
use App\Service\EmailConfirmationService;
use App\Service\MailProvider\MailProviderManager;
use App\Infrastructure\MailProviderRepository;
use App\Service\MailService;
use App\Service\NamespaceResolver;
use App\Service\SettingsService;
use App\Support\RequestDatabase;
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

        $name = trim((string) ($data['name'] ?? ''));

        // Store email, name and desired plan/app in session for after confirmation
        $_SESSION['auth_register'] = [
            'email' => $email,
            'name' => $name,
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

        // Fallback chain: request attribute → tenant schema → main domain schema.
        // Mail settings may only exist in the main domain's schema (edocs), not
        // in the product domain's schema (eforms, quizrace, etc.).
        if (!$manager instanceof MailProviderManager || !$manager->isConfigured()) {
            $tenantPdo = RequestDatabase::resolve($request);
            $repo = new MailProviderRepository($tenantPdo);
            $manager = new MailProviderManager(new SettingsService($tenantPdo), [], $repo, $namespace);
        }

        if (!$manager->isConfigured()) {
            $mainDomain = (string) (getenv('MAIN_DOMAIN') ?: '');
            $mainSchema = $mainDomain !== '' ? explode('.', $mainDomain)[0] : 'public';
            try {
                $mainPdo = Database::connectWithSchema($mainSchema);
                $mainRepo = new MailProviderRepository($mainPdo);
                $manager = new MailProviderManager(new SettingsService($mainPdo), [], $mainRepo, $mainSchema);
            } catch (\Throwable) {
                // Last resort — will fail with "not configured" below
            }
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

        // Read registration data from session (includes name, plan, app)
        $regData = $_SESSION['auth_register'] ?? [];
        unset($_SESSION['auth_register']);

        // Create or find account
        $pdo = Database::connectFromEnv();
        $accountService = new AccountService($pdo);
        $account = $accountService->findByEmail($email);

        if ($account === null) {
            $regName = trim((string) ($regData['name'] ?? ''));
            $accountId = $accountService->create($email, $regName !== '' ? $regName : null);
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

        $base = RouteContext::fromRequest($request)->getBasePath();

        // If plan/app were stored during registration, proceed to Stripe checkout
        $plan = (string) ($regData['plan'] ?? '');
        $app = (string) ($regData['app'] ?? '');

        if ($plan !== '' && $app !== '') {
            $target = $base . '/stripe/checkout?' . http_build_query(['plan' => $plan, 'app' => $app]);
        } else {
            $returnUrl = $_SESSION['auth_return_url'] ?? null;
            unset($_SESSION['auth_return_url']);
            $target = is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : $base . '/account/subscriptions';
        }

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
