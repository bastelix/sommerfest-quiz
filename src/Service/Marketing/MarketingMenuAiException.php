<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use RuntimeException;
use Throwable;

final class MarketingMenuAiException extends RuntimeException
{
    private string $errorCode;

    private int $status;

    public function __construct(string $message, string $errorCode, int $status, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->status = $status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
