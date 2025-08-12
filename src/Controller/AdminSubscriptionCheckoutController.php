<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Plan;
use App\Infrastructure\Database;
use App\Service\StripeService;
use App\Service\TenantService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Start a Stripe Checkout session from the admin subscription page.
 */
class AdminSubscriptionCheckoutController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $plan = Plan::tryFrom((string) ($data['plan'] ?? ''));
        if ($plan === null) {
            return $this->jsonError($response, 422, 'invalid plan');
        }

        $embedded = filter_var($data['embedded'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $host = $request->getUri()->getHost();
        $sub = explode('.', $host)[0];
        $tenant = null;
        $tenantService = null;
        $domainType = (string) $request->getAttribute('domainType');
        if ($domainType === 'main') {
            $path = dirname(__DIR__, 2) . '/data/profile.json';
            if (is_file($path)) {
                $tenant = json_decode((string) file_get_contents($path), true);
                if (!is_array($tenant)) {
                    $tenant = null;
                }
            }
        } else {
            $base = Database::connectFromEnv();
            $tenantService = new TenantService($base);
            $tenant = $tenantService->getBySubdomain($sub);
        }
        if ($tenant === null) {
            return $this->jsonError($response, 404, 'tenant not found');
        }

        $email = (string) ($tenant['imprint_email'] ?? '');
        $customerId = (string) ($tenant['stripe_customer_id'] ?? '');
        if ($email === '' && $customerId === '') {
            return $this->jsonError($response, 422, 'missing email');
        }

        if (!StripeService::isConfigured()) {
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
            return $this->jsonError($response, 422, 'invalid plan');
        }

        $service = new StripeService();
        if ($customerId === '' && $email !== '') {
            try {
                $customerId = $service->findCustomerIdByEmail($email) ?? $service->createCustomer(
                    $email,
                    $tenant['imprint_name'] ?? null
                );
                $tenant['stripe_customer_id'] = $customerId;
                if ($domainType === 'main') {
                    $path = dirname(__DIR__, 2) . '/data/profile.json';
                    $payload = json_encode($tenant, JSON_PRETTY_PRINT);
                    if ($payload !== false) {
                        file_put_contents($path, $payload);
                    }
                } else {
                    $tenantService?->updateProfile($sub, ['stripe_customer_id' => $customerId]);
                }
            } catch (\Throwable $e) {
                error_log($e->getMessage());
                return $this->jsonError($response, 500, 'internal error');
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
                $customerId === '' ? $email : null,
                $customerId !== '' ? $customerId : null,
                $tenant['subdomain'] ?? $sub,
                $embedded
            );
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            return $this->jsonError($response, 500, 'internal error');
        }

        if ($embedded) {
            $payload = json_encode([
                'client_secret' => $result,
                'publishable_key' => $service->getPublishableKey(),
            ]);
        } else {
            $payload = json_encode(['url' => $result]);
        }
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, int $status, string $message): Response
    {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
