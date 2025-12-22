<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PageAiJobRepository;
use App\Service\Marketing\PageAiErrorMapper;
use App\Service\Marketing\PageAiJobDispatcher;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

use function bin2hex;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function strtolower;
use function trim;
use function random_bytes;

final class PageAiController
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]{0,99}$/';

    private PageService $pageService;

    private NamespaceResolver $namespaceResolver;

    private PageAiPromptTemplateService $promptTemplateService;

    private PageAiJobDispatcher $jobDispatcher;

    private PageAiJobRepository $jobRepository;

    private PageAiErrorMapper $errorMapper;

    public function __construct(
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?PageAiPromptTemplateService $promptTemplateService = null,
        ?PageAiJobDispatcher $jobDispatcher = null,
        ?PageAiJobRepository $jobRepository = null,
        ?PageAiErrorMapper $errorMapper = null
    ) {
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->promptTemplateService = $promptTemplateService ?? new PageAiPromptTemplateService();
        $this->jobDispatcher = $jobDispatcher ?? new PageAiJobDispatcher();
        $this->jobRepository = $jobRepository ?? new PageAiJobRepository();
        $this->errorMapper = $errorMapper ?? new PageAiErrorMapper();
    }

    public function generate(Request $request, Response $response): Response
    {
        $payload = $this->decodePayload($request);
        if ($payload === null) {
            return $this->errorResponse(
                $response,
                'invalid_payload',
                'The request body must contain valid JSON.',
                400
            );
        }

        $slug = $this->normaliseSlug((string) ($payload['slug'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $theme = trim((string) ($payload['theme'] ?? ''));
        $colorScheme = trim((string) ($payload['colorScheme'] ?? $payload['color_scheme'] ?? ''));
        $problem = trim((string) ($payload['problem'] ?? ''));
        $promptTemplateId = trim((string) ($payload['promptTemplateId'] ?? $payload['prompt_template_id'] ?? ''));

        if ($slug === '' || $title === '' || $theme === '' || $colorScheme === '' || $problem === '') {
            return $this->errorResponse(
                $response,
                'missing_fields',
                'Slug, title, theme, colorScheme, and problem are required.',
                422
            );
        }

        if (!$this->isValidSlug($slug)) {
            return $this->errorResponse(
                $response,
                'invalid_slug',
                'The slug must use lowercase letters, numbers, and hyphens (max 100 characters).',
                422
            );
        }

        $promptTemplate = null;
        if ($promptTemplateId !== '') {
            $templateEntry = $this->promptTemplateService->findById($promptTemplateId);
            if ($templateEntry === null) {
                return $this->errorResponse(
                    $response,
                    'prompt_template_invalid',
                    'The requested AI prompt template was not found.',
                    422
                );
            }
            $promptTemplate = $templateEntry['template'];
        }

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if ($this->pageService->findByKey($namespace, $slug) === null) {
            return $this->errorResponse(
                $response,
                'page_not_found',
                'The requested page does not exist.',
                404
            );
        }

        $jobId = bin2hex(random_bytes(16));
        $payload = [
            'namespace' => $namespace,
            'slug' => $slug,
            'title' => $title,
            'theme' => $theme,
            'colorScheme' => $colorScheme,
            'problem' => $problem,
        ];
        if ($promptTemplate !== null) {
            $payload['promptTemplate'] = $promptTemplate;
        }

        try {
            $this->jobDispatcher->dispatch($jobId, $namespace, $slug, $payload);
        } catch (Throwable $exception) {
            return $this->errorResponse(
                $response,
                'dispatch_failed',
                'Failed to dispatch the AI generation job.',
                500
            );
        }

        return $this->successResponse($response, [
            'status' => 'queued',
            'namespace' => $namespace,
            'slug' => $slug,
            'jobId' => $jobId,
        ]);
    }

    public function status(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $jobId = trim((string) ($query['id'] ?? ''));
        if ($jobId === '') {
            return $this->errorResponse(
                $response,
                'missing_job_id',
                'A job id is required.',
                422
            );
        }

        $job = $this->jobRepository->find($jobId);
        if ($job === null) {
            return $this->errorResponse(
                $response,
                'job_not_found',
                'The requested job was not found.',
                404
            );
        }

        $payload = [
            'status' => $job['status'],
            'jobId' => $job['id'],
            'namespace' => $job['namespace'],
            'slug' => $job['slug'],
            'updatedAt' => $job['updated_at'],
        ];

        if ($job['status'] === 'done') {
            $payload['html'] = $job['result_html'] ?? '';
        }

        if ($job['status'] === 'failed') {
            $payload['error_code'] = $job['error_code'] ?? 'ai_error';
            $payload['error_message'] = $job['error_message']
                ?? $this->errorMapper->map(new \RuntimeException('ai_error'))['message'];
        }

        return $this->successResponse($response, $payload);
    }

    private function decodePayload(Request $request): ?array
    {
        $data = $request->getParsedBody();
        if (is_array($data)) {
            return $data;
        }

        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $raw = (string) $body;
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function normaliseSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function isValidSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function successResponse(Response $response, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    private function errorResponse(Response $response, string $error, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'error' => $error,
            'message' => $message,
        ], JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
