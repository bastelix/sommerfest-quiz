<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\MailProvider\MailProviderManager;
use DateTimeImmutable;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class NewsletterSubscriptionService
{
    private string $namespace;
    private PDO $pdo;

    private EmailConfirmationService $confirmationService;

    private MailProviderManager $providerManager;

    private ?MailService $mailService;

    private LoggerInterface $logger;

    public function __construct(
        PDO $pdo,
        EmailConfirmationService $confirmationService,
        MailProviderManager $providerManager,
        string $namespace,
        ?MailService $mailService = null,
        ?LoggerInterface $logger = null
    ) {
        $this->pdo = $pdo;
        $this->confirmationService = $confirmationService;
        $this->providerManager = $providerManager;
        $this->mailService = $mailService;
        $this->logger = $logger ?? new NullLogger();
        $this->namespace = $this->normalizeNamespace($namespace);
    }

    /**
     * Initiate a double opt-in flow for the given email address.
     *
     * @param array<string,mixed> $metadata   Additional context (ip, user_agent, source)
     * @param array<string,mixed> $attributes Attributes forwarded to the mail provider upon confirmation
     */
    public function requestSubscription(
        string $email,
        string $confirmationEndpoint,
        array $metadata = [],
        array $attributes = []
    ): void {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            throw new RuntimeException('Invalid email address for newsletter subscription.');
        }

        if ($this->mailService === null) {
            throw new RuntimeException('Mail service is not available for newsletter confirmations.');
        }

        $token = $this->confirmationService->createToken($email);
        $this->storePendingSubscription($email, $metadata, $attributes);
        $confirmationUrl = $this->buildConfirmationUrl($confirmationEndpoint, $token);
        $this->mailService->sendDoubleOptIn($email, $confirmationUrl);
    }

    /**
     * Confirm a pending subscription via token.
     */
    public function confirmSubscription(string $token): NewsletterConfirmationResult
    {
        $token = trim($token);
        if ($token === '') {
            return NewsletterConfirmationResult::failure();
        }

        $email = $this->confirmationService->confirmToken($token);
        if ($email === null) {
            return NewsletterConfirmationResult::failure();
        }

        $subscription = $this->findSubscription($email);
        if ($subscription === null) {
            $this->ensureSubscriptionRow($email);
            $subscription = ['attributes' => null, 'consent_metadata' => null];
        }

        $this->markSubscribed($email);
        $attributes = $this->decodeScalarMap($subscription['attributes'] ?? null);
        $metadata = $this->decodeScalarMap($subscription['consent_metadata'] ?? null);

        if ($this->providerManager->isConfigured()) {
            try {
                $this->providerManager->subscribe($email, $attributes);
            } catch (Throwable $exception) {
                $this->logger->error('Brevo subscription failed', [
                    'email' => $email,
                    'exception' => $exception,
                ]);
                throw new RuntimeException('Failed to subscribe contact to newsletter.', 0, $exception);
            }
        }

        return NewsletterConfirmationResult::success($metadata);
    }

    /**
     * Cancel an existing subscription.
     *
     * @param array<string,mixed> $metadata Additional context (ip, user_agent, source)
     */
    public function unsubscribe(string $email, array $metadata = []): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            return false;
        }

        $subscription = $this->findSubscription($email);
        $this->markUnsubscribed($email, $metadata, $subscription !== null);

        if ($this->providerManager->isConfigured()) {
            try {
                $this->providerManager->unsubscribe($email);
            } catch (Throwable $exception) {
                $this->logger->error('Brevo unsubscribe failed', [
                    'email' => $email,
                    'exception' => $exception,
                ]);
                throw new RuntimeException('Failed to unsubscribe contact from newsletter.', 0, $exception);
            }
        }

        return true;
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return strtolower($email);
    }

    private function buildConfirmationUrl(string $endpoint, string $token): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            throw new RuntimeException('Confirmation endpoint is required.');
        }

        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint . $separator . 'token=' . urlencode($token);
    }

    /**
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $attributes
     */
    private function storePendingSubscription(string $email, array $metadata, array $attributes): void
    {
        $now = $this->now();
        $metadataJson = $this->encodeScalarMap($metadata);
        $attributesJson = $this->encodeScalarMap($attributes);

        $sql = <<<'SQL'
INSERT INTO newsletter_subscriptions (namespace, email, status, consent_requested_at, consent_metadata, attributes)
VALUES (:namespace, :email, 'pending', :requested_at, :metadata, :attributes)
ON CONFLICT(namespace, email) DO UPDATE SET
    status = excluded.status,
    consent_requested_at = excluded.consent_requested_at,
    consent_metadata = excluded.consent_metadata,
    attributes = excluded.attributes,
    consent_confirmed_at = NULL,
    unsubscribe_at = NULL,
    unsubscribe_metadata = NULL
SQL;

        $this->executeStatement($sql, [
            'namespace' => $this->namespace,
            'email' => $email,
            'requested_at' => $now,
            'metadata' => $metadataJson,
            'attributes' => $attributesJson,
        ]);
    }

    private function ensureSubscriptionRow(string $email): void
    {
        $sql = <<<'SQL'
INSERT INTO newsletter_subscriptions (namespace, email, status, consent_requested_at)
VALUES (:namespace, :email, 'pending', :requested_at)
ON CONFLICT(namespace, email) DO NOTHING
SQL;

        $this->executeStatement($sql, [
            'namespace' => $this->namespace,
            'email' => $email,
            'requested_at' => $this->now(),
        ]);
    }

    private function markSubscribed(string $email): void
    {
        $sql = <<<'SQL'
UPDATE newsletter_subscriptions
   SET status = 'subscribed',
       consent_confirmed_at = :confirmed_at
 WHERE namespace = :namespace AND email = :email
SQL;

        $this->executeStatement($sql, [
            'namespace' => $this->namespace,
            'email' => $email,
            'confirmed_at' => $this->now(),
        ]);
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function markUnsubscribed(string $email, array $metadata, bool $exists): void
    {
        $now = $this->now();
        $metadataJson = $this->encodeScalarMap($metadata);

        if ($exists) {
            $sql = <<<'SQL'
UPDATE newsletter_subscriptions
   SET status = 'unsubscribed',
       unsubscribe_at = :unsubscribed_at,
       unsubscribe_metadata = :metadata
 WHERE namespace = :namespace AND email = :email
SQL;

            $this->executeStatement($sql, [
                'namespace' => $this->namespace,
                'email' => $email,
                'unsubscribed_at' => $now,
                'metadata' => $metadataJson,
            ]);

            return;
        }

        $sql = <<<'SQL'
INSERT INTO newsletter_subscriptions (namespace, email, status, consent_requested_at, unsubscribe_at, unsubscribe_metadata)
VALUES (:namespace, :email, 'unsubscribed', :requested_at, :unsubscribed_at, :metadata)
ON CONFLICT(namespace, email) DO UPDATE SET
    status = excluded.status,
    unsubscribe_at = excluded.unsubscribe_at,
    unsubscribe_metadata = excluded.unsubscribe_metadata
SQL;

        $this->executeStatement($sql, [
            'namespace' => $this->namespace,
            'email' => $email,
            'requested_at' => $now,
            'unsubscribed_at' => $now,
            'metadata' => $metadataJson,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findSubscription(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT email, status, consent_metadata, attributes FROM newsletter_subscriptions'
            . ' WHERE namespace = :namespace AND email = :email'
        );
        $stmt->execute([
            'namespace' => $this->namespace,
            'email' => $email,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function executeStatement(string $sql, array $params): void
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            $this->logger->error('Newsletter subscription persistence failed', [
                'sql' => $sql,
                'params' => $params,
                'exception' => $exception,
            ]);
            throw new RuntimeException('Failed to persist newsletter subscription state.', 0, $exception);
        }
    }

    private function encodeScalarMap(array $data): ?string
    {
        $normalized = $this->normalizeScalarMap($data);
        if ($normalized === []) {
            return null;
        }

        try {
            return json_encode($normalized, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to encode newsletter metadata', [
                'data' => $data,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @return array<string,scalar>
     */
    private function decodeScalarMap(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to decode newsletter metadata', [
                'json' => $json,
                'exception' => $exception,
            ]);

            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeScalarMap($decoded);
    }

    /**
     * @param array<array-key,mixed> $data
     * @return array<string,scalar>
     */
    private function normalizeScalarMap(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function normalizeNamespace(string $namespace): string
    {
        $normalized = strtolower(trim($namespace));
        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }
}
