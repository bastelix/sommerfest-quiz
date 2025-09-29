<?php

declare(strict_types=1);

namespace App\Controller;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\UserService;
use App\Service\StripeService;
use App\Infrastructure\Database;
use PDO;

/**
 * Display the onboarding wizard for creating a new tenant.
 */
class OnboardingController
{
    public function __invoke(Request $request, Response $response): Response {
        $view = Twig::fromRequest($request);
        $mainDomain = getenv('MAIN_DOMAIN')
            ?: getenv('DOMAIN')
            ?: $request->getUri()->getHost();

        $serviceUser = getenv('SERVICE_USER') ?: '';
        $servicePass = getenv('SERVICE_PASS') ?: '';
        $allowServiceLogin = getenv('ALLOW_SERVICE_LOGIN');
        $allowServiceLogin = $allowServiceLogin === false
            ? true
            : $allowServiceLogin === '1';

        if (
            $allowServiceLogin
            && $serviceUser !== ''
            && $servicePass !== ''
            && !isset($_SESSION['user'])
        ) {
            $pdo = $request->getAttribute('pdo');
            if (!$pdo instanceof PDO) {
                $pdo = Database::connectFromEnv();
            }
            $service = new UserService($pdo);
            $record = $service->getByUsername($serviceUser);
            if ($record !== null && (bool)$record['active']) {
                if (password_verify($servicePass, (string)$record['password'])) {
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

        $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $csrf;

        try {
            $stripeConfig = StripeService::isConfigured();
        } catch (\Throwable $e) {
            $stripeConfig = [
                'ok' => false,
                'missing' => [],
                'warnings' => [],
                'error' => 'Stripe-Konfig konnte nicht geprüft werden: '
                    . $e->getMessage(),
            ];
        }

        $stripeService = new StripeService();
        $publishableKey = $stripeService->getPublishableKey();
        $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
        $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
        $pricingTableId = getenv($prefix . 'PRICING_TABLE_ID') ?: '';

        return $view->render(
            $response,
            'onboarding.twig',
            [
                'main_domain' => $mainDomain,
                'logged_in' => $loggedIn,
                'reload_token' => $reloadToken,
                'csrf_token' => $csrf,
                'stripe_configured' => (bool) $stripeConfig['ok'],
                'stripe_missing' => $stripeConfig['missing'],
                'stripe_warnings' => $stripeConfig['warnings'],
                'stripe_error' => $stripeConfig['error'] ?? null,
                'stripe_publishable_key' => $publishableKey,
                'stripe_pricing_table_id' => $pricingTableId,
            ]
        );
    }
}
