<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when a username is blocked by policy.
 */
final class UsernameBlockedException extends RuntimeException
{
    public static function forExactMatch(string $username): self
    {
        return new self(sprintf('The username "%s" is not allowed.', $username));
    }

    public static function forPatternMatch(string $username): self
    {
        return new self(sprintf('The username "%s" is not allowed because it contains blocked terms.', $username));
    }
}
