<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Repository\PageAiJobRepository;
use Throwable;

use function App\runBackgroundProcess;
use function dirname;

final class PageAiJobDispatcher
{
    private string $scriptPath;

    private string $phpBinary;

    private ?PageAiJobRepository $jobRepository;

    public function __construct(
        ?string $phpBinary = null,
        ?string $scriptPath = null,
        ?PageAiJobRepository $jobRepository = null
    ) {
        $this->phpBinary = $phpBinary ?? PHP_BINARY;
        $this->scriptPath = $scriptPath ?? dirname(__DIR__, 3) . '/scripts/page_ai_generate.php';
        $this->jobRepository = $jobRepository;
    }

    /**
     * @throws \RuntimeException when the background process fails to start
     */
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
            $this->markJobFailed($jobId, $exception);

            throw new \RuntimeException(
                'Failed to start AI generation process.',
                0,
                $exception
            );
        }
    }

    private function markJobFailed(string $jobId, Throwable $exception): void
    {
        try {
            $repository = $this->jobRepository ?? new PageAiJobRepository();
            $repository->markFailed($jobId, 'dispatch_failed', $exception->getMessage());
        } catch (Throwable $inner) {
            error_log('Failed to mark AI job as failed: ' . $inner->getMessage());
        }
    }
}
