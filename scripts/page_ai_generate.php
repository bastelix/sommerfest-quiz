<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Repository\PageAiJobRepository;
use App\Service\Marketing\PageAiErrorMapper;
use App\Service\Marketing\PageAiGenerator;
use App\Service\PageService;
use JsonException;
use RuntimeException;
use Throwable;

try {
    $argv = $argv ?? [];
    if (count($argv) < 3) {
        throw new RuntimeException('Missing arguments for page AI generator.');
    }

    [$script, $jobId, $payloadEncoded] = array_pad($argv, 3, '');
    if ($jobId === '') {
        throw new RuntimeException('Job id must not be empty.');
    }

    $payload = decodePayload($payloadEncoded);

    $schemaEnv = getenv('APP_TENANT_SCHEMA');
    $schema = $schemaEnv === false || trim((string) $schemaEnv) === '' ? 'public' : (string) $schemaEnv;
    $pdo = Database::connectWithSchema($schema);

    $repository = new PageAiJobRepository($pdo);
    $job = $repository->find($jobId);
    if ($job === null) {
        throw new RuntimeException('Job not found.');
    }

    $repository->markProcessing($jobId);

    $generator = new PageAiGenerator();
    $pageService = new PageService($pdo);
    $errorMapper = new PageAiErrorMapper();

    try {
        $html = $generator->generate(
            (string) ($payload['slug'] ?? ''),
            (string) ($payload['title'] ?? ''),
            (string) ($payload['theme'] ?? ''),
            (string) ($payload['colorScheme'] ?? ''),
            (string) ($payload['problem'] ?? ''),
            isset($payload['promptTemplate']) ? (string) $payload['promptTemplate'] : null
        );
    } catch (RuntimeException $exception) {
        $mapped = $errorMapper->map($exception);
        $repository->markFailed($jobId, $mapped['code'], $mapped['message']);
        exit(1);
    }

    $namespace = (string) ($payload['namespace'] ?? $job['namespace'] ?? '');
    $slug = (string) ($payload['slug'] ?? $job['slug'] ?? '');
    if ($namespace === '' || $slug === '') {
        throw new RuntimeException('Missing page identifiers for AI generation.');
    }

    $pageService->save($namespace, $slug, $html);
    $repository->markDone($jobId, $html);
} catch (Throwable $exception) {
    $message = '[' . date('c') . '] Page AI generate failed: ' . $exception->getMessage() . PHP_EOL;
    fwrite(STDERR, $message);
    exit(1);
}

exit(0);

/**
 * @return array<string, mixed>
 */
function decodePayload(string $payloadEncoded): array
{
    if ($payloadEncoded === '') {
        return [];
    }

    $decoded = base64_decode($payloadEncoded, true);
    if ($decoded === false) {
        return [];
    }

    try {
        $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid job payload: ' . $exception->getMessage(), 0, $exception);
    }

    return is_array($payload) ? $payload : [];
}
