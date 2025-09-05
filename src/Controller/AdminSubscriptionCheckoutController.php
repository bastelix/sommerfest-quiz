<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Plan;
use App\Infrastructure\Database;
use App\Service\StripeService;
use App\Service\TenantService;
use App\Service\LogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Start a Stripe Checkout session from the admin subscription page.
 */
class AdminSubscriptionCheckoutController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $logger = LogService::create('stripe');
        try {
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            $headerToken = $request->getHeaderLine('X-CSRF-Token');
            if ($sessionToken === '' || $headerToken !== $sessionToken) {
                $logger->warning('CSRF token mismatch', [
                    'session' => $sessionToken,
                    'header' => $headerToken,
                ]);
                return $response->withStatus(403);
            }

            $data = json_decode((string) $request->getBody(), true);
            if (!is_array($data)) {
                $logger->warning('Invalid JSON payload');
                return $response->withStatus(400);
            }

            $plan = Plan::tryFrom((string) ($data['plan'] ?? ''));
            if ($plan === null) {
                $logger->warning('Invalid plan', ['plan' => $data['plan'] ?? null]);
                return $this->jsonError($response, 422, 'invalid plan');
            }

            $embedded = filter_var($data['embedded'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $host = $request->getUri()->getHost();
            $sub = explode('.', $host)[0];
            $domainType = (string) $request->getAttribute('domainType');
            $base = Database::connectFromEnv();
            $tenantService = new TenantService($base);
            $tenant = $domainType === 'main'
                ? $tenantService->getMainTenant()
                : $tenantService->getBySubdomain($sub);
            if ($tenant === null) {
                $logger->warning('Tenant not found', ['subdomain' => $sub, 'domainType' => $domainType]);
                return $this->jsonError($response, 404, 'tenant not found');
            }

            $email = (string) ($tenant['imprint_email'] ?? '');
            $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
            if ($email === '') {
                $payloadEmail = (string) ($data['email'] ?? '');
                if ($payloadEmail !== '' && filter_var($payloadEmail, FILTER_VALIDATE_EMAIL)) {
                    $email = $payloadEmail;
                    $tenant['imprint_email'] = $email;
                    $tenantService->updateProfile(
                        $domainType === 'main' ? 'main' : $sub,
                        ['imprint_email' => $email]
                    );
                }
            }
            if ($email === '' && $customerId === '') {
                $logger->warning('Missing tenant email', ['tenant' => $tenant['subdomain'] ?? $sub]);
                return $this->jsonError($response, 422, 'missing email');
            }

            if (!StripeService::isConfigured()['ok']) {
                $logger->error('Stripe configuration incomplete');
                return $this->jsonError($response, 503, 'service unavailable');
            }

            $useSandbox = filter_var(getenv('STRIPE_SANDBOX'), FILTER_VALIDATE_BOOLEAN);
            $prefix = $useSandbox ? 'STRIPE_SANDBOX_' : 'STRIPE_';
            $priceMap = [
                Plan::STARTER->value => getenv($prefix . 'PRICE_STARTER') ?: '',
                Plan::STANDARD->value => getenv($prefix . 'PRICE_STANDARD') ?: '',
                Plan::PROFESSIONAL->value => getenv($prefix . 'PRICE_PROFESSIONAL') ?: '',
            ];
            $priceId = $priceMap[$plan->value];
            if ($priceId === '') {
                $logger->error('Price ID missing', ['plan' => $plan->value]);
                return $this->jsonError($response, 422, 'invalid plan');
            }

            $service = new StripeService();
            if ($customerId === '') {
                try {
                    $customerId = $service->findCustomerIdByEmail($email) ?? $service->createCustomer(
                        $email,
                        $tenant['imprint_name'] ?? null
                    );
                    $tenant['stripe_customer_id'] = $customerId;
                    $tenantService->updateProfile(
                        $domainType === 'main' ? 'main' : $sub,
                        ['stripe_customer_id' => $customerId]
                    );
                } catch (\Throwable $e) {
                    $logger->error('Failed to create/find customer', ['error' => $e->getMessage()]);
                    return $this->jsonError($response, 503, 'service unavailable');
                }
            }

            $uri = $request->getUri();
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
            $successUrl = $baseUrl . '/admin/subscription?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $baseUrl . '/admin/subscription';

            try {
                $result = $service->createCheckoutSession(
                    $priceId,
                    $successUrl,
                    $cancelUrl,
                    $plan->value,
                    $customerId === '' ? $email : null,
                    $customerId !== '' ? $customerId : null,
                    $tenant['subdomain'] ?? $sub,
                    null,
                    $embedded
                );
            } catch (\Throwable $e) {
                $logger->error('Checkout session creation failed', ['error' => $e->getMessage()]);
                return $this->jsonError($response, 503, 'service unavailable');
            }

            if ($embedded) {
                $payload = json_encode([
                    'client_secret' => $result,
                    'publishable_key' => $service->getPublishableKey(),
                ]);
            } else {
                $payload = json_encode(['url' => $result]);
            }
            $logger->info('Checkout session created', ['embedded' => $embedded]);
            $response->getBody()->write($payload !== false ? $payload : '{}');
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $logger->error('Unexpected checkout failure', ['error' => $e->getMessage()]);
            return $this->jsonError($response, 500, 'internal error');
        }
    }

    private function jsonError(Response $response, int $status, string $message): Response
    {
        $payload = ['error' => $message];
        $log = LogService::tail('stripe');
        if ($log !== '') {
            $payload['log'] = $log;
        }
        $json = json_encode($payload);
        $response->getBody()->write($json !== false ? $json : '{}');
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
