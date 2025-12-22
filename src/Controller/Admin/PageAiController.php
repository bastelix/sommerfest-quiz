<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Marketing\PageAiGenerator;
use App\Service\Marketing\PageAiPromptTemplateService;
use App\Service\NamespaceResolver;
use App\Service\PageService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;

use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function strtolower;
use function trim;

final class PageAiController
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]{0,99}$/';

    private PageAiGenerator $generator;

    private PageService $pageService;

    private NamespaceResolver $namespaceResolver;

    private PageAiPromptTemplateService $promptTemplateService;

    public function __construct(
        ?PageAiGenerator $generator = null,
        ?PageService $pageService = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?PageAiPromptTemplateService $promptTemplateService = null
    ) {
        $this->generator = $generator ?? new PageAiGenerator();
        $this->pageService = $pageService ?? new PageService();
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->promptTemplateService = $promptTemplateService ?? new PageAiPromptTemplateService();
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

        try {
            $html = $this->generator->generate($slug, $title, $theme, $colorScheme, $problem, $promptTemplate);
        } catch (RuntimeException $exception) {
            return $this->handleGenerationError($response, $exception);
        }

        $this->pageService->save($namespace, $slug, $html);

        return $this->successResponse($response, [
            'status' => 'ok',
            'namespace' => $namespace,
            'slug' => $slug,
            'html' => $html,
        ]);
    }

    private function handleGenerationError(Response $response, RuntimeException $exception): Response
    {
        $message = $exception->getMessage();

        if ($message === PageAiGenerator::ERROR_PROMPT_MISSING) {
            return $this->errorResponse(
                $response,
                'prompt_missing',
                'The AI prompt template is not configured.',
                500
            );
        }

        if ($message === PageAiGenerator::ERROR_RESPONDER_MISSING) {
            return $this->errorResponse(
                $response,
                'ai_unavailable',
                'The AI responder is not configured. Check RAG_CHAT_SERVICE_URL (and RAG_CHAT_SERVICE_TOKEN, RAG_CHAT_SERVICE_MODEL, RAG_CHAT_SERVICE_TIMEOUT) in your environment configuration.',
                503
            );
        }

        if ($message === PageAiGenerator::ERROR_EMPTY_RESPONSE) {
            return $this->errorResponse(
                $response,
                'ai_empty',
                'The AI responder returned an empty response.',
                502
            );
        }

        if ($message === PageAiGenerator::ERROR_INVALID_HTML) {
            return $this->errorResponse(
                $response,
                'ai_invalid_html',
                'The AI responder returned HTML that did not pass validation.',
                422
            );
        }

        if (str_starts_with($message, PageAiGenerator::ERROR_RESPONDER_FAILED . ':')) {
            $details = trim(substr($message, strlen(PageAiGenerator::ERROR_RESPONDER_FAILED . ':')));
            if ($this->isTimeout($exception)) {
                return $this->errorResponse(
                    $response,
                    'ai_timeout',
                    $details !== ''
                        ? sprintf('The AI responder did not respond in time. %s', $details)
                        : 'The AI responder did not respond in time.',
                    504
                );
            }

            return $this->errorResponse(
                $response,
                'ai_failed',
                $details !== ''
                    ? sprintf('The AI responder failed to generate HTML. %s', $details)
                    : 'The AI responder failed to generate HTML.',
                503
            );
        }

        return $this->errorResponse(
            $response,
            'ai_error',
            'The AI responder failed to generate HTML.',
            503
        );
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

    private function isTimeout(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isTimeout($previous);
        }

        return false;
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
