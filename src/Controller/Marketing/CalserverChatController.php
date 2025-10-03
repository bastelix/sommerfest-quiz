<?php

declare(strict_types=1);

namespace App\Controller\Marketing;

use App\Service\RagChat\RagChatResponse;
use App\Service\RagChat\RagChatService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;

/**
 * JSON endpoint that exposes the RAG chatbot for the marketing site.
 */
final class CalserverChatController
{
    private RagChatService $service;

    public function __construct(?RagChatService $service = null)
    {
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

        try {
            $chatResponse = $this->service->answer($payload['question'], $locale);
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
}
