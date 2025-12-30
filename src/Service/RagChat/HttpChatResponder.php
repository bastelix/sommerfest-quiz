<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function getenv;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use function usleep;

/**
 * Sends chat prompts to the configured HTTP endpoint.
 */
class HttpChatResponder implements ChatResponderInterface
{
    private const DEFAULT_TIMEOUT = 60.0;
    private const MIN_TIMEOUT = 1.0;
    private const MAX_RETRIES = 2;
    private const RETRY_BACKOFF_SECONDS = 0.25;

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
        $this->timeout = $this->resolveTimeout($timeout);

        $clientOptions = [
            'timeout' => $this->timeout,
            'http_errors' => false,
        ];

        $this->httpClient = $httpClient ?? new Client($clientOptions);
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

        $attempt = 0;
        /** @var ResponseInterface|null $response */
        $response = null;
        while (true) {
            try {
                $response = $this->httpClient->request('POST', $this->endpoint, [
                    'json' => $this->buildRequestPayload($messages, $context),
                    'headers' => $this->buildHeaders(),
                    'timeout' => $this->timeout,
                ]);
                break;
            } catch (GuzzleException $exception) {
                $attempt++;
                if ($attempt >= self::MAX_RETRIES || !$this->isTimeoutException($exception)) {
                    throw new RuntimeException('Failed to contact chat service: ' . $exception->getMessage(), 0, $exception);
                }

                usleep((int) (self::RETRY_BACKOFF_SECONDS * 1_000_000 * $attempt));
            }
        }

        if ($response === null) {
            throw new RuntimeException('Failed to contact chat service after retry attempts.');
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

    private function resolveTimeout(?float $override): float
    {
        if ($override !== null) {
            return $this->clampTimeout($override);
        }

        $configured = $this->envOrNull('RAG_CHAT_SERVICE_TIMEOUT');
        if ($configured !== null) {
            $parsed = (float) $configured;
            if ($parsed > 0.0) {
                return $this->clampTimeout($parsed);
            }
        }

        return self::DEFAULT_TIMEOUT;
    }

    private function clampTimeout(float $timeout): float
    {
        if ($timeout < self::MIN_TIMEOUT) {
            return self::MIN_TIMEOUT;
        }

        return $timeout;
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
            $content = $this->normaliseMessageContent($message['content'] ?? null);
            if ($content !== null) {
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
                    $content = $this->normaliseMessageContent($message['content'] ?? null);
                    if ($content !== null) {
                        return $content;
                    }
                }
                $text = $choice['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        if (array_key_exists('output', $payload)) {
            $content = $this->normaliseMessageContent($payload['output']);
            if ($content !== null) {
                return $content;
            }
        }

        if (array_key_exists('output_text', $payload) && is_string($payload['output_text']) && trim($payload['output_text']) !== '') {
            return $payload['output_text'];
        }

        return null;
    }

    /**
     * @param mixed $content
     */
    private function normaliseMessageContent($content): ?string
    {
        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        if (!is_array($content)) {
            return null;
        }

        $segments = $this->collectTextSegments($content);
        if ($segments === []) {
            return null;
        }

        $combined = implode('', $segments);
        return trim($combined) === '' ? null : $combined;
    }

    /**
     * @param mixed $node
     * @return list<string>
     */
    private function collectTextSegments($node): array
    {
        if (is_string($node)) {
            return [$node];
        }

        if (!is_array($node)) {
            return [];
        }

        $segments = [];

        foreach (['text', 'value'] as $key) {
            if (isset($node[$key]) && is_string($node[$key])) {
                $segments[] = $node[$key];
            }
        }

        foreach (['content', 'output'] as $nestedKey) {
            if (array_key_exists($nestedKey, $node)) {
                $nestedSegments = $this->collectTextSegments($node[$nestedKey]);
                if ($nestedSegments !== []) {
                    foreach ($nestedSegments as $segment) {
                        $segments[] = $segment;
                    }
                }
            }
        }

        foreach ($node as $key => $value) {
            if (is_int($key)) {
                $nestedSegments = $this->collectTextSegments($value);
                if ($nestedSegments !== []) {
                    foreach ($nestedSegments as $segment) {
                        $segments[] = $segment;
                    }
                }
            }
        }

        return $segments;
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

    private function isTimeoutException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out') || str_contains($message, 'curl error 28')) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isTimeoutException($previous);
        }

        return false;
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
