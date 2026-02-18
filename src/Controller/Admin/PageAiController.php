<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PageAiJobRepository;
use App\Service\Marketing\PageAiJobDispatcher;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\NamespaceDesignFileRepository;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;
use function ucfirst;

final class PageAiController
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]{0,99}$/';
    private const JOB_ID_PATTERN = '/^[a-f0-9]{32}$/';
    private const BLOCK_CONTRACT_TEMPLATE_ID = 'landing-block-contract';

    private const DEFAULT_PRIMARY_COLOR = '#1e87f0';
    private const DEFAULT_ACCENT_COLOR = '#f59e0b';
    private const DEFAULT_BACKGROUND_COLOR = '#0f172a';

    private PageService $pageService;

    private NamespaceResolver $namespaceResolver;

    private PageAiPromptTemplateService $promptTemplateService;

    private PageAiJobRepository $jobRepository;

    private PageAiJobDispatcher $jobDispatcher;

    private NamespaceDesignFileRepository $designFileRepository;

    public function __construct(
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?PageAiPromptTemplateService $promptTemplateService = null,
        ?PageAiJobRepository $jobRepository = null,
        ?PageAiJobDispatcher $jobDispatcher = null,
        ?NamespaceDesignFileRepository $designFileRepository = null
    ) {
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->promptTemplateService = $promptTemplateService ?? new PageAiPromptTemplateService();
        $this->jobRepository = $jobRepository ?? new PageAiJobRepository();
        $this->jobDispatcher = $jobDispatcher ?? new PageAiJobDispatcher();
        $this->designFileRepository = $designFileRepository ?? new NamespaceDesignFileRepository();
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
        $problem = trim((string) ($payload['problem'] ?? ''));

        if ($slug === '' || $title === '' || $problem === '') {
            return $this->errorResponse(
                $response,
                'missing_fields',
                'Slug, title, and problem are required.',
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

        $templateEntry = $this->promptTemplateService->findById(self::BLOCK_CONTRACT_TEMPLATE_ID);
        if ($templateEntry === null) {
            return $this->errorResponse(
                $response,
                'prompt_template_invalid',
                'The block-contract AI prompt template was not found.',
                422
            );
        }
        $promptTemplate = $templateEntry['template'];

        $namespace = $this->namespaceResolver->resolve($request)->getNamespace();
        if ($this->pageService->findByKey($namespace, $slug) === null) {
            return $this->errorResponse(
                $response,
                'page_not_found',
                'The requested page does not exist.',
                404
            );
        }

        $design = $this->resolveDesignTokens($namespace);

        $jobId = $this->jobRepository->createJob(
            $namespace,
            $slug,
            $title,
            $design['theme'],
            $design['colorScheme'],
            $problem,
            $promptTemplate
        );

        $this->jobDispatcher->dispatch($jobId);

        return $this->successResponse($response, [
            'status' => 'queued',
            'jobId' => $jobId,
        ], 202);
    }

    public function status(Request $request, Response $response): Response
    {
        $jobId = trim((string) ($request->getQueryParams()['id'] ?? ''));
        if ($jobId === '') {
            return $this->errorResponse(
                $response,
                'missing_job_id',
                'The request must include a job id.',
                400
            );
        }

        if (!$this->isValidJobId($jobId)) {
            return $this->errorResponse(
                $response,
                'invalid_job_id',
                'The job id format is invalid.',
                422
            );
        }

        $job = $this->jobRepository->getJob($jobId);
        if ($job === null) {
            return $this->errorResponse(
                $response,
                'job_not_found',
                'The requested job does not exist.',
                404
            );
        }

        $payload = [
            'status' => $job['status'],
            'jobId' => $job['job_id'],
        ];

        if ($job['status'] === PageAiJobRepository::STATUS_DONE) {
            $payload['html'] = $job['html'] ?? '';
            $payload['content'] = $job['html'] ?? '';
            $payload['namespace'] = $job['namespace'];
            $payload['slug'] = $job['slug'];
        } elseif ($job['status'] === PageAiJobRepository::STATUS_FAILED) {
            $payload['error'] = $job['error_code'] ?? 'ai_error';
            $payload['message'] = $job['error_message'] ?? 'The AI responder failed to generate content.';
        }

        return $this->successResponse($response, $payload);
    }

    /**
     * @return array{theme:string,colorScheme:string,primaryColor:string,accentColor:string,backgroundColor:string}
     */
    private function resolveDesignTokens(string $namespace): array
    {
        $designData = $this->designFileRepository->loadFile($namespace);

        $meta = $designData['meta'] ?? [];
        $config = $designData['config'] ?? [];
        $tokens = is_array($config) ? ($config['designTokens'] ?? []) : [];
        $brand = is_array($tokens) ? ($tokens['brand'] ?? []) : [];
        $colors = is_array($config) ? ($config['colors'] ?? []) : [];

        $theme = is_array($meta) ? trim((string) ($meta['name'] ?? '')) : '';
        if ($theme === '') {
            $theme = ucfirst($namespace);
        }

        $primaryColor = is_array($brand) ? trim((string) ($brand['primary'] ?? '')) : '';
        if ($primaryColor === '') {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }

        $accentColor = is_array($brand) ? trim((string) ($brand['accent'] ?? '')) : '';
        if ($accentColor === '') {
            $accentColor = self::DEFAULT_ACCENT_COLOR;
        }

        $backgroundColor = is_array($colors) ? trim((string) ($colors['background'] ?? '')) : '';
        if ($backgroundColor === '') {
            $backgroundColor = self::DEFAULT_BACKGROUND_COLOR;
        }

        $colorScheme = sprintf(
            'Primary: %s; Background: %s; Accent: %s',
            $primaryColor,
            $backgroundColor,
            $accentColor
        );

        return [
            'theme' => $theme,
            'colorScheme' => $colorScheme,
            'primaryColor' => $primaryColor,
            'accentColor' => $accentColor,
            'backgroundColor' => $backgroundColor,
        ];
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

    private function isValidJobId(string $jobId): bool
    {
        return preg_match(self::JOB_ID_PATTERN, $jobId) === 1;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function successResponse(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
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
