<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\MarketingSlugResolver;
use App\Service\RagChat\RagChatResponse;
use App\Service\RagChat\RagChatService;
use App\Service\RagChat\RagChatServiceInterface;
use App\Support\DomainNameHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;
use Slim\Routing\RouteContext;

/**
 * JSON endpoint that exposes the RAG chatbot for the marketing site.
 */
final class CalserverChatController
{
    private RagChatServiceInterface $service;

    private ?string $slug;

    public function __construct(?string $slug = null, ?RagChatServiceInterface $service = null)
    {
        $this->slug = $slug;
        $this->service = $service ?? new RagChatService();
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            $payload = $this->decodeRequest($request);
        } catch (RuntimeException $exception) {
            $response->getBody()->write(json_encode(['error' => 'invalid'], JSON_THROW_ON_ERROR));

            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }

        $locale = (string) ($request->getAttribute('lang') ?? 'de');

        $domain = $this->resolveDomain($request);

        try {
            $chatResponse = $this->service->answer($payload['question'], $locale, $domain);
        } catch (RuntimeException $exception) {
            error_log('Calserver chat validation failed: ' . $exception->getMessage());

            $response->getBody()->write(json_encode(['error' => 'invalid'], JSON_THROW_ON_ERROR));

            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        } catch (Throwable $exception) {
            error_log('Calserver chat failed: ' . $exception->getMessage());

            $response->getBody()->write(json_encode(['error' => 'unavailable'], JSON_THROW_ON_ERROR));

            return $response
                ->withStatus(503)
                ->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($this->normaliseResponse($chatResponse), JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array{question:string}
     */
    private function decodeRequest(Request $request): array
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $raw = (string) $body;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (!is_array($data)) {
            throw new RuntimeException('Missing payload.');
        }

        $question = trim((string) ($data['question'] ?? ''));
        if ($question === '') {
            throw new RuntimeException('Question missing.');
        }

        return ['question' => $question];
    }

    /**
     * @return array<string, mixed>
     */
    private function normaliseResponse(RagChatResponse $chatResponse): array
    {
        $context = [];
        foreach ($chatResponse->getContext() as $item) {
            $context[] = [
                'label' => $item->getLabel(),
                'snippet' => $item->getSnippet(),
                'score' => $item->getScore(),
                'metadata' => $item->getMetadata(),
            ];
        }

        return [
            'question' => $chatResponse->getQuestion(),
            'answer' => $chatResponse->getAnswer(),
            'context' => $context,
        ];
    }

    private function resolveDomain(Request $request): ?string
    {
        $slug = $this->slug ?? $this->detectSlugFromRequest($request);
        if ($slug !== null) {
            $baseSlug = MarketingSlugResolver::resolveBaseSlug($slug);
            $normalizedSlug = DomainNameHelper::normalize($baseSlug, false);
            if ($normalizedSlug !== '') {
                return $normalizedSlug;
            }
        }

        $host = (string) $request->getUri()->getHost();
        $normalizedHost = DomainNameHelper::normalize($host) ?: '';

        return $normalizedHost === '' ? null : $normalizedHost;
    }

    private function detectSlugFromRequest(Request $request): ?string
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
        } catch (RuntimeException $exception) {
            return null;
        }

        if ($route === null) {
            return null;
        }

        $arguments = $route->getArguments();
        foreach (['marketingSlug', 'landingSlug', 'slug'] as $key) {
            $value = $arguments[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
