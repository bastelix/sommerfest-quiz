<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use Throwable;

use function App\runBackgroundProcess;
use function dirname;

final class PageAiJobDispatcher
{
    private string $scriptPath;

    private string $phpBinary;

    public function __construct(?string $phpBinary = null, ?string $scriptPath = null)
    {
        $this->phpBinary = $phpBinary ?? PHP_BINARY;
        $this->scriptPath = $scriptPath ?? dirname(__DIR__, 3) . '/scripts/page_ai_generate.php';
    }

    public function dispatch(string $jobId): void
    {
        if ($jobId === '') {
            return;
        }

        $arguments = [$this->scriptPath, $jobId];

        try {
            runBackgroundProcess($this->phpBinary, $arguments);
        } catch (Throwable $exception) {
            error_log('Failed to dispatch page AI job: ' . $exception->getMessage());
        }
    }
}
