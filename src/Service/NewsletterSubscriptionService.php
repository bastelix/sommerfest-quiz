<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\MailProvider\MailProviderManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class NewsletterSubscriptionService
{
    private string $namespace;

    private MailProviderManager $providerManager;

    private LoggerInterface $logger;

    public function __construct(
        MailProviderManager $providerManager,
        string $namespace,
        ?LoggerInterface $logger = null
    ) {
        $this->providerManager = $providerManager;
        $this->logger = $logger ?? new NullLogger();
        $this->namespace = $this->normalizeNamespace($namespace);
    }

    /**
     * Forward a subscription request directly to the configured mail provider.
     *
     * @param array<string,mixed> $metadata   Additional context (ip, user_agent, source)
     * @param array<string,mixed> $attributes Attributes forwarded to the mail provider
     */
    public function subscribe(string $email, array $metadata = [], array $attributes = []): void
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            throw new RuntimeException('Invalid email address for newsletter subscription.');
        }

        if (!$this->providerManager->isConfigured()) {
            throw new RuntimeException('Mail provider is not configured for newsletter subscriptions.');
        }

        $payload = $this->buildPayload($metadata, $attributes);

        try {
            $this->providerManager->subscribe($email, $payload);
        } catch (Throwable $exception) {
            $this->logger->error('Newsletter subscription failed', [
                'email' => $email,
                'payload' => $payload,
                'exception' => $exception,
            ]);

            throw new RuntimeException('Failed to subscribe contact to newsletter.', 0, $exception);
        }
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

        if (!$this->providerManager->isConfigured()) {
            throw new RuntimeException('Mail provider is not configured for newsletter subscriptions.');
        }

        try {
            $this->providerManager->unsubscribe($email);
        } catch (Throwable $exception) {
            $this->logger->error('Brevo unsubscribe failed', [
                'email' => $email,
                'metadata' => $this->buildPayload($metadata),
                'exception' => $exception,
            ]);
            throw new RuntimeException('Failed to unsubscribe contact from newsletter.', 0, $exception);
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

    private function normalizeNamespace(string $namespace): string
    {
        $normalized = strtolower(trim($namespace));
        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }

    /**
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $attributes
     * @return array<string,scalar>
     */
    private function buildPayload(array $metadata = [], array $attributes = []): array
    {
        $payload = $this->normalizeScalarMap($attributes);

        $meta = $this->normalizeScalarMap($metadata);
        foreach ($meta as $key => $value) {
            $payload['META_' . strtoupper($key)] = $value;
        }

        if (!isset($payload['NEWSLETTER_NAMESPACE'])) {
            $payload['NEWSLETTER_NAMESPACE'] = $this->namespace;
        }

        return $payload;
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
}
