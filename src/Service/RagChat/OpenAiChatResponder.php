<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use GuzzleHttp\ClientInterface;
use RuntimeException;

use function array_values;
use function in_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Specialised responder for the OpenAI chat completions API.
 */
final class OpenAiChatResponder extends HttpChatResponder
{
    private const OPTION_MAP = [
        'RAG_CHAT_SERVICE_TEMPERATURE' => 'temperature',
        'RAG_CHAT_SERVICE_TOP_P' => 'top_p',
        'RAG_CHAT_SERVICE_PRESENCE_PENALTY' => 'presence_penalty',
        'RAG_CHAT_SERVICE_FREQUENCY_PENALTY' => 'frequency_penalty',
        'RAG_CHAT_SERVICE_MAX_COMPLETION_TOKENS' => 'max_completion_tokens',
    ];

    private const DEFAULT_MAX_COMPLETION_TOKENS = 4096;

    private string $model;

    /**
     * @var array<string,mixed>
     */
    private array $options;

    public function __construct(
        ?string $endpoint = null,
        ?ClientInterface $httpClient = null,
        ?string $apiToken = null,
        ?float $timeout = null,
        ?string $model = null,
        ?array $options = null
    ) {
        parent::__construct($endpoint, $httpClient, $apiToken, $timeout);

        $this->model = $model ?? $this->loadModelFromEnv();
        $this->options = $options !== null ? $this->normaliseOptions($options) : $this->loadOptionsFromEnv();
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>> $context
     * @return array<string,mixed>
     */
    protected function buildRequestPayload(array $messages, array $context): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        foreach ($this->options as $key => $value) {
            if ($key === 'model' || $key === 'messages') {
                continue;
            }
            $payload[$key] = $value;
        }

        return $payload;
    }

    protected function requiresContext(): bool
    {
        return false;
    }

    private function loadModelFromEnv(): string
    {
        $model = $this->envOrNull('RAG_CHAT_SERVICE_MODEL');
        if ($model === null) {
            throw new RuntimeException('OpenAI chat model is not configured.');
        }

        return $model;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadOptionsFromEnv(): array
    {
        $options = ['max_completion_tokens' => self::DEFAULT_MAX_COMPLETION_TOKENS];

        foreach (self::OPTION_MAP as $envKey => $payloadKey) {
            $value = $this->envOrNull($envKey);
            if ($value === null) {
                continue;
            }

            $options[$payloadKey] = $value;
        }

        return $this->normaliseOptions($options);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function normaliseOptions(array $options): array
    {
        $allowed = array_values(self::OPTION_MAP);
        $normalised = [];

        foreach ($options as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            if ($key === 'max_completion_tokens') {
                if (is_int($value)) {
                    $normalised[$key] = $value;
                    continue;
                }
                if (is_string($value) && is_numeric($value)) {
                    $normalised[$key] = (int) $value;
                }
                continue;
            }

            if (is_float($value) || is_int($value)) {
                $normalised[$key] = (float) $value;
                continue;
            }

            if (is_string($value) && is_numeric($value)) {
                $normalised[$key] = (float) $value;
            }
        }

        if (!isset($normalised['max_completion_tokens'])) {
            $normalised['max_completion_tokens'] = self::DEFAULT_MAX_COMPLETION_TOKENS;
        }

        return $normalised;
    }
}
