<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

use function array_key_exists;
use function getenv;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
use function trim;

/**
 * Sends chat prompts to the configured HTTP endpoint.
 */
class HttpChatResponder implements ChatResponderInterface
{
    private const DEFAULT_TIMEOUT = 30.0;

    private ClientInterface $httpClient;

    private string $endpoint;

    private ?string $apiToken;

    private float $timeout;

    public function __construct(
        ?string $endpoint = null,
        ?ClientInterface $httpClient = null,
        ?string $apiToken = null,
        ?float $timeout = null
    ) {
        $this->endpoint = $endpoint ?? (string) getenv('RAG_CHAT_SERVICE_URL');
        if ($this->endpoint === '') {
            throw new RuntimeException('Chat service URL is not configured.');
        }

        $this->apiToken = $apiToken ?? ($this->envOrNull('RAG_CHAT_SERVICE_TOKEN'));
        $this->timeout = $timeout ?? self::DEFAULT_TIMEOUT;
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => $this->timeout,
            'http_errors' => false,
        ]);
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>|mixed> $context
     */
    public function respond(array $messages, array $context): string
    {
        if ($this->requiresContext() && $context === []) {
            throw new RuntimeException('Chat responder requires context to build an answer.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'json' => $this->buildRequestPayload($messages, $context),
                'headers' => $this->buildHeaders(),
                'timeout' => $this->timeout,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to contact chat service: ' . $exception->getMessage(), 0, $exception);
        }

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Chat service returned HTTP %d: %s', $status, $body));
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Chat service responded with an invalid payload.');
        }

        $answer = $this->extractAnswer($payload);
        if ($answer === null) {
            throw new RuntimeException('Chat service did not provide an answer.');
        }

        return trim($answer);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiToken !== null && $this->apiToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiToken;
        }

        return $headers;
    }

    /**
     * @param list<array<string,mixed>|mixed> $context
     * @return list<array{id:string,text:string,score:float,metadata:array<string,mixed>}>
     */
    protected function normaliseContext(array $context): array
    {
        $normalised = [];
        foreach ($context as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (string) $item['id'] : '';
            $text = isset($item['text']) ? (string) $item['text'] : '';
            $score = isset($item['score']) ? (float) $item['score'] : 0.0;
            $metadata = [];
            if (isset($item['metadata'])) {
                /** @var mixed $rawMetadata */
                $rawMetadata = $item['metadata'];
                if (is_array($rawMetadata)) {
                    /** @var array<string, mixed> $rawMetadata */
                    $metadata = $rawMetadata;
                }
            }

            $normalised[] = [
                'id' => $id,
                'text' => $text,
                'score' => $score,
                'metadata' => $metadata,
            ];
        }

        return $normalised;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractAnswer(array $payload): ?string
    {
        if (array_key_exists('answer', $payload) && is_string($payload['answer']) && trim($payload['answer']) !== '') {
            return $payload['answer'];
        }

        if (array_key_exists('message', $payload) && is_array($payload['message'])) {
            $message = $payload['message'];
            $content = $message['content'] ?? null;
            if (is_string($content) && trim($content) !== '') {
                return $content;
            }
        }

        if (array_key_exists('choices', $payload) && is_array($payload['choices'])) {
            foreach ($payload['choices'] as $choice) {
                if (!is_array($choice)) {
                    continue;
                }
                $message = $choice['message'] ?? null;
                if (is_array($message)) {
                    $content = $message['content'] ?? null;
                    if (is_string($content) && trim($content) !== '') {
                        return $content;
                    }
                }
                $text = $choice['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    protected function envOrNull(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>|mixed> $context
     * @return array<string,mixed>
     */
    protected function buildRequestPayload(array $messages, array $context): array
    {
        return [
            'messages' => $messages,
            'context' => $this->normaliseContext($context),
        ];
    }

    protected function requiresContext(): bool
    {
        return true;
    }
}
