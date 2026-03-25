<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Database;
use App\Service\LagoBillingService;
use App\Service\LogService;
use App\Service\NamespaceSubscriptionService;
use App\Service\TenantService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Handle Lago billing webhook events.
 *
 * Lago signs every webhook with RSA-SHA256 (X-Lago-Signature header).
 * The public key is fetched from the Lago API and cached in memory.
 *
 * Handled events:
 *  - subscription.started     → create/activate namespace project
 *  - subscription.terminated  → suspend namespace project
 *  - invoice.payment_status_updated → reactivate on payment success
 */
final class LagoWebhookController
{
    private LoggerInterface $logger;

    public function __invoke(Request $request, Response $response): Response
    {
        $this->logger = LogService::create('lago');

        $rawBody = (string) $request->getBody();
        $signature = $request->getHeaderLine('X-Lago-Signature');

        // ── Signature verification ───────────────────────────────
        if (!$this->verifyWebhook($rawBody, $signature)) {
            $this->logger->warning('Webhook signature verification failed');
            return $response->withStatus(400);
        }

        // ── Parse payload ────────────────────────────────────────
        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || !isset($payload['webhook_type'])) {
            $this->logger->warning('Invalid webhook payload');
            return $response->withStatus(400);
        }

        $webhookType = (string) $payload['webhook_type'];
        $objectType = (string) ($payload['object_type'] ?? '');
        $object = $payload[$objectType] ?? $payload;

        $this->logger->info('Received webhook', ['type' => $webhookType]);

        // ── Route to handler ─────────────────────────────────────
        $pdo = Database::connectFromEnv();
        $nsSvc = new NamespaceSubscriptionService($pdo);

        $tenantService = $request->getAttribute('tenantService');
        if (!$tenantService instanceof TenantService) {
            $tenantService = new TenantService($pdo);
        }

        match ($webhookType) {
            'subscription.started' => $this->handleSubscriptionStarted($object, $nsSvc, $tenantService),
            'subscription.terminated' => $this->handleSubscriptionTerminated($object, $nsSvc),
            'invoice.payment_status_updated' => $this->handleInvoicePaymentStatus($object, $nsSvc),
            default => $this->logger->info('Unhandled webhook type', ['type' => $webhookType]),
        };

        return $response->withStatus(200);
    }

    // ------------------------------------------------------------------
    //  Event handlers
    // ------------------------------------------------------------------

    private function handleSubscriptionStarted(
        array $sub,
        NamespaceSubscriptionService $nsSvc,
        TenantService $tenantService,
    ): void {
        $externalCustomerId = (string) ($sub['external_customer_id'] ?? '');
        $planCode = (string) ($sub['plan_code'] ?? '');
        $lagoSubId = (string) ($sub['lago_id'] ?? '');
        $externalSubId = (string) ($sub['external_id'] ?? '');

        if ($externalCustomerId === '') {
            $this->logger->warning('subscription.started: missing external_customer_id');
            return;
        }

        // The external_customer_id doubles as the namespace slug for edocs
        $slug = $externalCustomerId;

        $this->logger->info('Creating/activating namespace', [
            'slug' => $slug,
            'plan' => $planCode,
        ]);

        // Ensure namespace project exists
        $nsSvc->findOrCreate($slug);

        // Update plan and Lago billing info
        if ($planCode !== '') {
            try {
                $nsSvc->updatePlan($slug, $planCode);
            } catch (\InvalidArgumentException) {
                // Plan code doesn't match enum — store it anyway via Lago fields
                $this->logger->warning('Unknown plan code from Lago', ['plan' => $planCode]);
            }
        }

        $nsSvc->updateLagoInfo($slug, [
            'lago_customer_id' => $externalCustomerId,
            'lago_subscription_id' => $lagoSubId !== '' ? $lagoSubId : $externalSubId,
            'lago_plan_code' => $planCode,
            'lago_status' => 'active',
        ]);
    }

    private function handleSubscriptionTerminated(
        array $sub,
        NamespaceSubscriptionService $nsSvc,
    ): void {
        $externalCustomerId = (string) ($sub['external_customer_id'] ?? '');

        if ($externalCustomerId === '') {
            return;
        }

        $slug = $externalCustomerId;
        $this->logger->info('Suspending namespace', ['slug' => $slug]);

        $nsSvc->updateLagoInfo($slug, [
            'lago_status' => 'terminated',
        ]);
    }

    private function handleInvoicePaymentStatus(
        array $invoice,
        NamespaceSubscriptionService $nsSvc,
    ): void {
        $paymentStatus = (string) ($invoice['payment_status'] ?? '');
        $customer = $invoice['customer'] ?? [];
        $externalCustomerId = (string) ($customer['external_id'] ?? '');

        if ($externalCustomerId === '' || $paymentStatus !== 'succeeded') {
            return;
        }

        $slug = $externalCustomerId;
        $this->logger->info('Reactivating namespace after payment', ['slug' => $slug]);

        $nsSvc->updateLagoInfo($slug, [
            'lago_status' => 'active',
        ]);
    }

    // ------------------------------------------------------------------
    //  Signature verification
    // ------------------------------------------------------------------

    private function verifyWebhook(string $rawBody, string $signature): bool
    {
        // In development/testing, allow skipping verification
        $webhookSecret = getenv('LAGO_WEBHOOK_SECRET') ?: '';
        if ($webhookSecret === '' && $signature === '') {
            $this->logger->warning('Lago webhook verification skipped (no secret configured)');
            return true;
        }

        // Try RSA verification with public key from Lago API
        $publicKey = $this->fetchPublicKey();
        if ($publicKey === '') {
            $this->logger->error('Could not fetch Lago webhook public key');
            return false;
        }

        return LagoBillingService::verifySignature($rawBody, $signature, $publicKey);
    }

    private function fetchPublicKey(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $check = LagoBillingService::isConfigured();
        if (!$check['ok']) {
            return '';
        }

        try {
            $service = new LagoBillingService();
            $cached = $service->getWebhookPublicKey();
            return $cached;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch Lago public key', ['error' => $e->getMessage()]);
            return '';
        }
    }
}
