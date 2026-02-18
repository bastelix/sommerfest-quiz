<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repository\PageAiJobRepository;
use App\Service\Marketing\PageAiBlockContractValidator;
use App\Service\Marketing\PageAiErrorMapper;
use App\Service\Marketing\PageAiGenerator;
use App\Service\PageService;
use RuntimeException;
use Throwable;

try {
    $argv = $argv ?? [];
    if (count($argv) < 2) {
        throw new RuntimeException('Missing job id for page AI generation.');
    }

    $jobId = trim((string) ($argv[1] ?? ''));
    if ($jobId === '') {
        throw new RuntimeException('Job id must not be empty.');
    }

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
} catch (Throwable $exception) {
    $jobId = $jobId ?? '';
    if (is_string($jobId) && $jobId !== '' && isset($job) && is_array($job)) {
        try {
            $mapper = new PageAiErrorMapper();
            $mapped = $mapper->map($exception);
            $jobRepository = $jobRepository ?? new PageAiJobRepository();
            $jobRepository->markFailed($jobId, $mapped['error_code'], $mapped['message']);
        } catch (Throwable $inner) {
            error_log('Failed to update page AI job: ' . $inner->getMessage());
        }
    }

    $message = '[' . date('c') . '] Page AI generation failed: ' . $exception->getMessage() . PHP_EOL;
    fwrite(STDERR, $message);
    exit(1);
}

exit(0);
