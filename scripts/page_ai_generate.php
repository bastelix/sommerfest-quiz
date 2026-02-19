<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\App\Support\EnvLoader::loadAndSet(__DIR__ . '/../.env');

use App\Repository\PageAiJobRepository;
use App\Service\Marketing\PageAiBlockContractValidator;
use App\Service\Marketing\PageAiErrorMapper;
use App\Service\Marketing\PageAiGenerator;
use App\Service\PageService;
use RuntimeException;
use Throwable;

$_pageAiJobId = trim((string) ($argv[1] ?? ''));
$_pageAiCompleted = false;

register_shutdown_function(static function () use (&$_pageAiJobId, &$_pageAiCompleted): void {
    if ($_pageAiCompleted || $_pageAiJobId === '') {
        return;
    }

    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $message = sprintf(
        'Fatal error during AI generation: %s in %s on line %d',
        $error['message'],
        $error['file'],
        $error['line']
    );

    error_log('[page_ai_generate] ' . $message);

    try {
        $repository = new PageAiJobRepository();
        $repository->markFailed($_pageAiJobId, 'ai_error', $message);
    } catch (Throwable $inner) {
        error_log('[page_ai_generate] Failed to mark job as failed after fatal: ' . $inner->getMessage());
    }
});

try {
    $argv = $argv ?? [];
    if (count($argv) < 2 || $_pageAiJobId === '') {
        throw new RuntimeException('Missing or empty job id for page AI generation.');
    }

    $jobId = $_pageAiJobId;

    $jobRepository = new PageAiJobRepository();
    $job = $jobRepository->getPendingJob($jobId);
    if ($job === null) {
        throw new RuntimeException('Job not found or already processed.');
    }

    $validator = new PageAiBlockContractValidator();
    $generator = new PageAiGenerator(null, null, null, null, $validator);
    $content = $generator->generate(
        $job['slug'],
        $job['title'],
        $job['theme'],
        $job['color_scheme'],
        $job['problem'],
        $job['prompt_template'],
        $job['namespace']
    );

    $pageService = new PageService();
    $pageService->save($job['namespace'], $job['slug'], $content);

    $jobRepository->markDone($jobId, $content);
    $_pageAiCompleted = true;
} catch (Throwable $exception) {
    $_pageAiCompleted = true;

    if ($_pageAiJobId !== '') {
        try {
            $mapper = new PageAiErrorMapper();
            $mapped = $mapper->map($exception);
            $jobRepository = $jobRepository ?? new PageAiJobRepository();
            $jobRepository->markFailed($_pageAiJobId, $mapped['error_code'], $mapped['message']);
        } catch (Throwable $inner) {
            error_log('[page_ai_generate] Failed to update job: ' . $inner->getMessage());
        }
    }

    $message = '[' . date('c') . '] Page AI generation failed: ' . $exception->getMessage() . PHP_EOL;
    fwrite(STDERR, $message);
    exit(1);
}

exit(0);
