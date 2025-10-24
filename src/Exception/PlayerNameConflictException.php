<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown when a player name conflicts with an existing entry.
 */
class PlayerNameConflictException extends RuntimeException
{
}
