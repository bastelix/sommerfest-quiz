<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Value object representing the outcome of a newsletter confirmation attempt.
 */
final class NewsletterConfirmationResult
{
    /**
     * @param array<string, scalar> $metadata
     */
    private function __construct(
        private readonly bool $success,
        private readonly array $metadata,
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param array<string, scalar> $metadata
     */
    public static function success(array $metadata): self
    {
        return new self(true, $metadata);
    }

    /**
     * Create a failed result without metadata.
     */
    public static function failure(): self
    {
        return new self(false, []);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, scalar>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
