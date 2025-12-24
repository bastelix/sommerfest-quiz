<?php
declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use function sprintf;

final class DuplicateUsernameBlocklistException extends RuntimeException
{
    public static function forTerm(string $term): self
    {
        return new self(sprintf('The username "%s" is already blocked.', $term));
    }
}
