<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

final class NamespaceValidator
{
    public const MAX_LENGTH = 100;
    private const PATTERN = '^[a-z0-9][a-z0-9-]*$';

    public function normalize(string $namespace): string
    {
        return strtolower(trim($namespace));
    }

    public function normalizeCandidate(mixed $candidate): ?string
    {
        if (!is_string($candidate)) {
            return null;
        }

        $normalized = $this->normalize($candidate);
        if ($normalized === '') {
            return null;
        }

        if (!$this->isValidLength($normalized) || !$this->isValidFormat($normalized)) {
            return null;
        }

        return $normalized;
    }

    public function isValidLength(string $namespace): bool
    {
        $length = mb_strlen($namespace);

        return $length > 0 && $length <= self::MAX_LENGTH;
    }

    public function isValidFormat(string $namespace): bool
    {
        return preg_match('/' . self::PATTERN . '/', $namespace) === 1;
    }

    public function assertValid(string $namespace): void
    {
        if ($namespace === '') {
            throw new InvalidArgumentException('namespace-empty');
        }

        if (!$this->isValidLength($namespace)) {
            throw new InvalidArgumentException('namespace-length');
        }

        if (!$this->isValidFormat($namespace)) {
            throw new InvalidArgumentException('namespace-format');
        }
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getMaxLength(): int
    {
        return self::MAX_LENGTH;
    }
}
