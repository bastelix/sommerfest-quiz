<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the Lago billing API.
 *
 * Communicates with a self-hosted Lago instance at LAGO_API_URL.
 * All billing secrets stay on the edocs server — SaaS products
 * only receive webhooks and never call this service directly.
 */
final class LagoBillingService
{
    private Client $http;
    private string $apiKey;
    private ?LoggerInterface $logger;

    public function __construct(
        ?string $apiUrl = null,
        ?string $apiKey = null,
        ?Client $client = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->apiKey = $apiKey ?? (getenv('LAGO_API_KEY') ?: '');
        $baseUrl = rtrim($apiUrl ?? (getenv('LAGO_API_URL') ?: 'http://lago-api:3000'), '/');
        $this->http = $client ?? new Client([
            'base_uri' => $baseUrl . '/api/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);
        $this->logger = $logger;
    }

    /**
     * Check whether the Lago API is reachable and the key is valid.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function isConfigured(): array
    {
        $key = getenv('LAGO_API_KEY') ?: '';
        $url = getenv('LAGO_API_URL') ?: '';

        if ($key === '') {
            return ['ok' => false, 'error' => 'LAGO_API_KEY missing'];
        }
        if ($url === '') {
            return ['ok' => false, 'error' => 'LAGO_API_URL missing'];
        }

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    //  Customers
    // ------------------------------------------------------------------

    /**
     * Create or update a Lago customer (upsert by external_id).
     *
     * @param array{
     *   external_id:string,
     *   name?:string,
     *   email?:string,
     *   currency?:string,
     *   timezone?:string,
     *   billing_configuration?:array<string,mixed>,
     *   metadata?:list<array{key:string,value:string,display_in_invoice?:bool}>
     * } $data
     * @return array The created/updated customer object
     */
    public function upsertCustomer(array $data): array
    {
        return $this->post('customers', ['customer' => $data]);
    }

    /**
     * Retrieve a customer by external ID.
     */
    public function getCustomer(string $externalId): ?array
    {
        return $this->get("customers/{$externalId}");
    }

    // ------------------------------------------------------------------
    //  Subscriptions
    // ------------------------------------------------------------------

    /**
     * Create a new subscription.
     *
     * @param array{
     *   external_customer_id:string,
     *   plan_code:string,
     *   external_id?:string,
     *   name?:string,
     *   billing_time?:string,
     *   subscription_at?:string,
     *   ending_at?:string
     * } $data
     */
    public function createSubscription(array $data): array
    {
        return $this->post('subscriptions', ['subscription' => $data]);
    }

    /**
     * Terminate a subscription by external ID.
     */
    public function terminateSubscription(string $externalId): ?array
    {
        return $this->delete("subscriptions/{$externalId}");
    }

    /**
     * Retrieve a subscription by external ID.
     */
    public function getSubscription(string $externalId): ?array
    {
        return $this->get("subscriptions/{$externalId}");
    }

    // ------------------------------------------------------------------
    //  Plans
    // ------------------------------------------------------------------

    /**
     * List all available plans.
     *
     * @return list<array>
     */
    public function listPlans(): array
    {
        $result = $this->get('plans');
        return $result['plans'] ?? [];
    }

    // ------------------------------------------------------------------
    //  Invoices
    // ------------------------------------------------------------------

    /**
     * List invoices, optionally filtered by external customer ID.
     *
     * @return list<array>
     */
    public function listInvoices(?string $externalCustomerId = null): array
    {
        $query = $externalCustomerId !== null
            ? ['external_customer_id' => $externalCustomerId]
            : [];

        $result = $this->get('invoices', $query);
        return $result['invoices'] ?? [];
    }

    // ------------------------------------------------------------------
    //  Webhook verification
    // ------------------------------------------------------------------

    /**
     * Fetch the RSA public key used to verify webhook signatures.
     */
    public function getWebhookPublicKey(): string
    {
        $result = $this->get('webhooks/public_key');
        $encoded = $result['webhook_endpoint']['public_key']
            ?? $result['public_key']
            ?? (is_string($result) ? $result : '');

        return base64_decode($encoded, true) ?: '';
    }

    /**
     * Verify a Lago webhook signature.
     *
     * Lago signs the raw body with RSA-SHA256. The signature is sent
     * as a Base64-encoded string in the X-Lago-Signature header.
     */
    public static function verifySignature(string $rawBody, string $signatureHeader, string $publicKeyPem): bool
    {
        if ($signatureHeader === '' || $publicKeyPem === '') {
            return false;
        }

        $signature = base64_decode($signatureHeader, true);
        if ($signature === false) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if ($pubKey === false) {
            return false;
        }

        return openssl_verify($rawBody, $signature, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    // ------------------------------------------------------------------
    //  Events (usage-based billing)
    // ------------------------------------------------------------------

    /**
     * Ingest a usage event.
     *
     * @param array{
     *   transaction_id:string,
     *   external_subscription_id:string,
     *   code:string,
     *   timestamp?:int,
     *   properties?:array<string,mixed>
     * } $data
     */
    public function createEvent(array $data): array
    {
        return $this->post('events', ['event' => $data]);
    }

    // ------------------------------------------------------------------
    //  HTTP helpers
    // ------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private function post(string $uri, array $body): array
    {
        try {
            $response = $this->http->post($uri, ['json' => $body]);
            $decoded = json_decode((string) $response->getBody(), true);
            return is_array($decoded) ? $decoded : [];
        } catch (GuzzleException $e) {
            $this->logger?->error('Lago API POST failed', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string,string> $query
     * @return array<string,mixed>
     */
    private function get(string $uri, array $query = []): ?array
    {
        try {
            $options = $query !== [] ? ['query' => $query] : [];
            $response = $this->http->get($uri, $options);
            $decoded = json_decode((string) $response->getBody(), true);
            return is_array($decoded) ? $decoded : null;
        } catch (GuzzleException $e) {
            $this->logger?->error('Lago API GET failed', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function delete(string $uri): ?array
    {
        try {
            $response = $this->http->delete($uri);
            $decoded = json_decode((string) $response->getBody(), true);
            return is_array($decoded) ? $decoded : null;
        } catch (GuzzleException $e) {
            $this->logger?->error('Lago API DELETE failed', [
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
