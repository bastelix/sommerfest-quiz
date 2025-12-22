<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Repository\PageAiJobRepository;
use Throwable;

use function App\runBackgroundProcess;
use function base64_encode;
use function dirname;
use function getenv;
use function json_encode;
use function putenv;

final class PageAiJobDispatcher
{
    private string $scriptPath;

    private string $phpBinary;

    private PageAiJobRepository $repository;

    public function __construct(
        ?PageAiJobRepository $repository = null,
        ?string $phpBinary = null,
        ?string $scriptPath = null
    ) {
        $this->repository = $repository ?? new PageAiJobRepository();
        $this->phpBinary = $phpBinary ?? PHP_BINARY;
        $this->scriptPath = $scriptPath ?? dirname(__DIR__, 3) . '/scripts/page_ai_generate.php';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(
        string $jobId,
        string $namespace,
        string $slug,
        array $payload
    ): void {
        $this->repository->create($jobId, $namespace, $slug, $payload);

        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $payloadEncoded = base64_encode($payloadJson);

        $arguments = [
            $this->scriptPath,
            $jobId,
            $payloadEncoded,
        ];

        $previousSchema = getenv('APP_TENANT_SCHEMA');
        $shouldRestoreSchema = true;
        if ($namespace !== '') {
            putenv('APP_TENANT_SCHEMA=' . $namespace);
        }

        try {
            runBackgroundProcess($this->phpBinary, $arguments, dirname(__DIR__, 3) . '/logs/page_ai_generate.log');
        } catch (Throwable $exception) {
            $this->repository->markFailed($jobId, 'dispatch_failed', $exception->getMessage());
            error_log('Failed to dispatch page AI job: ' . $exception->getMessage());
        } finally {
            if ($shouldRestoreSchema) {
                if ($previousSchema === false) {
                    putenv('APP_TENANT_SCHEMA');
                } else {
                    putenv('APP_TENANT_SCHEMA=' . $previousSchema);
                }
            }
        }
    }
}
