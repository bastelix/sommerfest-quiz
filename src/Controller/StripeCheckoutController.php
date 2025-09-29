<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Plan;
use App\Service\StripeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Start a Stripe Checkout session during onboarding.
 */
class StripeCheckoutController
{
    public function __invoke(Request $request, Response $response): Response {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return $response->withStatus(400);
        }

        $plan = Plan::tryFrom((string) ($data['plan'] ?? ''));
        $email = (string) ($data['email'] ?? '');
        $subdomain = (string) ($data['subdomain'] ?? '');

        if ($plan === null) {
            return $this->jsonError($response, 422, 'invalid plan');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError($response, 422, 'invalid email');
        }
        if ($subdomain === '' || !preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return $this->jsonError($response, 422, 'invalid subdomain');
        }

        if (!StripeService::isConfigured()['ok']) {
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
            error_log('Missing priceId for plan ' . $plan->value);
            return $this->jsonError($response, 422, 'invalid plan');
        }

        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $successUrl = $base . '/onboarding/checkout/{CHECKOUT_SESSION_ID}';
        $cancelUrl = $base . '/onboarding/checkout/{CHECKOUT_SESSION_ID}';

        $service = $request->getAttribute('stripeService');
        if (!$service instanceof StripeService) {
            $service = new StripeService();
        }
        try {
            $url = $service->createCheckoutSession(
                $priceId,
                $successUrl,
                $cancelUrl,
                $plan->value,
                $email,
                null,
                $subdomain,
                7
            );
            error_log('createCheckoutSession returned ' . $url);
        } catch (Throwable $e) {
            error_log('Stripe API error for plan ' . $plan->value . ': ' . $e->getMessage());
            $this->reportError($e);
            return $this->jsonError($response, 500, 'internal error');
        }

        $payload = json_encode(['url' => $url]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, int $status, string $message): Response {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload !== false ? $payload : '{}');
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function reportError(Throwable $e): void {
        error_log($e->getMessage());
        error_log($e->getTraceAsString());

        $file = $e->getFile();
        $line = $e->getLine();
        $snippet = '';

        if (is_readable($file)) {
            $lines = file($file);
            $start = max($line - 3, 0);
            $excerpt = array_slice($lines, $start, 6, true);
            foreach ($excerpt as $num => $code) {
                $snippet .= sprintf('%d: %s', $num + 1, $code);
            }
        }

        $body = sprintf(
            "Fehler: %s\nDatei: %s:%d\n\nCodeausschnitt:\n%s\nStacktrace:\n%s\n",
            $e->getMessage(),
            $file,
            $line,
            $snippet,
            $e->getTraceAsString()
        );

        $headers = "From: noreply@quizrace.app\r\n";
        @mail('support@quizrace.app', 'Fehler beim Onboarding Schritt 3', $body, $headers);
    }
}
