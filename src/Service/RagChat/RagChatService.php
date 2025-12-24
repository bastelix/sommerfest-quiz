<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Infrastructure\Database;
use App\Service\SettingsService;
use App\Support\DomainNameHelper;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function array_merge;
use function array_unique;
use function array_values;
use function getenv;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function rtrim;
use function str_ends_with;
use function strtolower;
use function trim;
use function strpos;
use function substr;

/**
 * High-level facade that prepares responses for the marketing chat endpoint.
 */
final class RagChatService implements RagChatServiceInterface
{
    private const MESSAGE_TEMPLATES = [
        'de' => [
            'intro' => 'Basierend auf der Wissensbasis habe ich folgende Hinweise gefunden:',
            'no_results' => 'Ich konnte keine passenden Informationen in der Dokumentation finden. '
                . 'Bitte formuliere deine Frage anders oder schränke das Thema ein.',
            'question' => 'Frage',
        ],
        'en' => [
            'intro' => 'Based on our knowledge base I found the following hints:',
            'no_results' => 'I could not find matching information in the documentation. Please rephrase your question '
                . 'or narrow down the topic.',
            'question' => 'Question',
        ],
    ];

    private const SYSTEM_PROMPTS = [
        'de' => 'Du bist ein hilfreicher Assistent für die QuizRace-Dokumentation. '
            . 'Beantworte Fragen ausschließlich anhand der bereitgestellten Kontexte.',
        'en' => 'You are a helpful assistant for the QuizRace documentation. '
            . 'Answer questions only by relying on the supplied context snippets.',
    ];

    private const CONTEXT_HEADERS = [
        'de' => "Kontext aus der Wissensbasis:\n",
        'en' => "Context from the knowledge base:\n",
    ];

    private const ENV_KEY_MAP = [
        'rag_chat_service_url' => 'RAG_CHAT_SERVICE_URL',
        'rag_chat_service_driver' => 'RAG_CHAT_SERVICE_DRIVER',
        'rag_chat_service_force_openai' => 'RAG_CHAT_SERVICE_FORCE_OPENAI',
        'rag_chat_service_token' => 'RAG_CHAT_SERVICE_TOKEN',
        'rag_chat_service_model' => 'RAG_CHAT_SERVICE_MODEL',
        'rag_chat_service_temperature' => 'RAG_CHAT_SERVICE_TEMPERATURE',
        'rag_chat_service_top_p' => 'RAG_CHAT_SERVICE_TOP_P',
        'rag_chat_service_max_tokens' => 'RAG_CHAT_SERVICE_MAX_TOKENS',
        'rag_chat_service_presence_penalty' => 'RAG_CHAT_SERVICE_PRESENCE_PENALTY',
        'rag_chat_service_frequency_penalty' => 'RAG_CHAT_SERVICE_FREQUENCY_PENALTY',
    ];

    private const OPENAI_OPTION_KEYS = [
        'rag_chat_service_temperature' => 'temperature',
        'rag_chat_service_top_p' => 'top_p',
        'rag_chat_service_max_tokens' => 'max_tokens',
        'rag_chat_service_presence_penalty' => 'presence_penalty',
        'rag_chat_service_frequency_penalty' => 'frequency_penalty',
    ];

    private const DEFAULT_LOCALE = 'de';

    private string $indexPath;

    private string $domainIndexBase;

    private ?ChatResponderInterface $chatResponder;

    /** @var array<string,mixed>|null */
    private ?array $chatSettings = null;

    private bool $chatSettingsLoaded = false;

    /** @var callable|null */
    private $settingsLoader;

    private ?string $lastResponderError = null;

    public function __construct(
        ?string $indexPath = null,
        ?string $domainIndexBase = null,
        ?ChatResponderInterface $chatResponder = null,
        ?callable $settingsLoader = null
    ) {
        $basePath = dirname(__DIR__, 3);
        $this->indexPath = $indexPath ?? $basePath . '/data/rag-chatbot/index.json';
        $this->domainIndexBase = $domainIndexBase ?? $basePath . '/data/rag-chatbot/domains';
        $this->chatResponder = $chatResponder;
        $this->settingsLoader = $settingsLoader;
    }

    /**
     * Provide the configured chat responder if available.
     */
    public function getChatResponder(): ?ChatResponderInterface
    {
        return $this->chatResponder ?? $this->createDefaultResponder();
    }

    public function answer(
        string $question,
        string $locale = self::DEFAULT_LOCALE,
        ?string $domain = null
    ): RagChatResponse {
        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('Question must not be empty.');
        }

        $globalIndex = SemanticIndex::load($this->indexPath);
        $contextResults = [];
        $seenChunks = [];

        $normalizedDomain = $domain !== null ? DomainNameHelper::normalize($domain) : '';
        if ($normalizedDomain !== '') {
            foreach ($this->buildDomainCandidates($normalizedDomain) as $candidate) {
                $domainIndexPath = $this->domainIndexBase . '/' . $candidate . '/index.json';
                if (!is_file($domainIndexPath)) {
                    continue;
                }

                try {
                    $domainIndex = SemanticIndex::load($domainIndexPath);
                } catch (RuntimeException $exception) {
                    error_log('Failed to load domain-specific RAG index: ' . $exception->getMessage());
                    continue;
                }

                $candidateResults = [];
                foreach ($domainIndex->search($question, 4, 0.05) as $result) {
                    $chunkId = $result->getChunkId();
                    if (isset($seenChunks[$chunkId])) {
                        continue;
                    }
                    $seenChunks[$chunkId] = true;
                    $candidateResults[] = ['result' => $result, 'domain' => $candidate];
                }

                if ($candidateResults !== []) {
                    $contextResults = array_merge($contextResults, $candidateResults);
                    break;
                }
            }
        }

        if ($contextResults === []) {
            if ($normalizedDomain !== '') {
                $messages = self::MESSAGE_TEMPLATES[$locale] ?? self::MESSAGE_TEMPLATES[self::DEFAULT_LOCALE];

                return new RagChatResponse($question, $messages['no_results'], []);
            }

            foreach ($globalIndex->search($question, 4, 0.05) as $result) {
                if (isset($seenChunks[$result->getChunkId()])) {
                    continue;
                }
                $contextResults[] = ['result' => $result, 'domain' => null];
            }
        }

        $contextResults = array_slice($contextResults, 0, 6);
        $messages = self::MESSAGE_TEMPLATES[$locale] ?? self::MESSAGE_TEMPLATES[self::DEFAULT_LOCALE];

        if ($contextResults === []) {
            return new RagChatResponse($question, $messages['no_results'], []);
        }

        $contextItems = [];
        $contextPayload = [];
        foreach ($contextResults as $entry) {
            $prepared = $this->buildContextEntry($entry['result'], $locale, $entry['domain']);
            $contextItems[] = $prepared['item'];
            $contextPayload[] = $prepared['payload'];
        }

        $chatMessages = $this->buildChatMessages($question, $contextItems, $locale);
        $answer = $this->requestChatAnswer($chatMessages, $contextPayload);

        if ($answer === null) {
            $answer = $this->composeFallbackAnswer($question, $contextItems, $messages, $this->lastResponderError);
        }

        return new RagChatResponse($question, $answer, $contextItems);
    }

    /**
     * @param array{intro:string,no_results:string,question:string} $messages
     * @param list<RagChatContextItem> $context
     */
    private function composeFallbackAnswer(
        string $question,
        array $context,
        array $messages,
        ?string $logMessage = null
    ): string {
        $lines = [$messages['intro']];
        foreach ($context as $index => $item) {
            $number = $index + 1;
            $lines[] = sprintf('%d. %s: %s', $number, $item->getLabel(), $item->getSnippet());
        }
        $lines[] = '';
        $lines[] = sprintf('%s: %s', $messages['question'], $question);

        if ($logMessage !== null && $logMessage !== '') {
            $lines[] = '';
            $lines[] = sprintf('[Log] %s', $logMessage);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{
     *     item:RagChatContextItem,
     *     payload:array{id:string,text:string,score:float,metadata:array<string,mixed>}
     * }
     */
    private function buildContextEntry(SearchResult $result, string $locale, ?string $originDomain = null): array
    {
        $metadata = $result->getMetadata();
        if ($originDomain !== null) {
            $metadata['domain'] = $originDomain;
        }

        $label = $this->buildLabel($metadata, $result->getChunkId(), $locale);
        $snippet = $this->summariseText($result->getText());
        $rawScore = $result->getScore();

        $item = new RagChatContextItem($label, $snippet, round($rawScore, 4), $metadata);

        return [
            'item' => $item,
            'payload' => [
                'id' => $result->getChunkId(),
                'text' => $result->getText(),
                'score' => round($rawScore, 6),
                'metadata' => $metadata,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function buildDomainCandidates(string $normalizedDomain): array
    {
        $candidates = [$normalizedDomain];
        $dotPosition = strpos($normalizedDomain, '.');
        if ($dotPosition !== false && $dotPosition > 0) {
            $subdomain = substr($normalizedDomain, 0, $dotPosition);
            if ($subdomain !== $normalizedDomain) {
                $candidates[] = $subdomain;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param list<RagChatContextItem> $context
     * @return list<array{role:string,content:string}>
     */
    private function buildChatMessages(string $question, array $context, string $locale): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($locale)],
        ];

        if ($context !== []) {
            $lines = [$this->contextHeader($locale)];
            foreach ($context as $index => $item) {
                $lines[] = sprintf('[%d] %s\n%s', $index + 1, $item->getLabel(), $item->getSnippet());
            }
            $messages[] = [
                'role' => 'system',
                'content' => implode("\n\n", $lines),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }

    private function systemPrompt(string $locale): string
    {
        return self::SYSTEM_PROMPTS[$locale] ?? self::SYSTEM_PROMPTS[self::DEFAULT_LOCALE];
    }

    private function contextHeader(string $locale): string
    {
        return self::CONTEXT_HEADERS[$locale] ?? self::CONTEXT_HEADERS[self::DEFAULT_LOCALE];
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>> $context
     */
    private function requestChatAnswer(array $messages, array $context): ?string
    {
        $this->lastResponderError = null;

        $responder = $this->chatResponder ?? $this->createDefaultResponder();
        if ($responder === null) {
            $this->lastResponderError = $this->lastResponderError
                ?? 'Chat responder unavailable: no endpoint configured.';
            return null;
        }

        try {
            return $responder->respond($messages, $context);
        } catch (RuntimeException $exception) {
            $message = 'Chat responder failed: ' . $exception->getMessage();
            error_log($message);
            $this->lastResponderError = $message;
        }

        return null;
    }

    private function createDefaultResponder(): ?ChatResponderInterface
    {
        try {
            $endpoint = $this->detectEndpoint();
            if ($endpoint !== null && $this->isOpenAiEndpoint($endpoint)) {
                $endpoint = $this->normaliseOpenAiEndpoint($endpoint);
                $token = $this->getChatSettingValue('rag_chat_service_token');
                $model = $this->getChatSettingValue('rag_chat_service_model');
                $options = $this->buildOpenAiOptions();

                $this->chatResponder = new OpenAiChatResponder(
                    $endpoint,
                    null,
                    $token,
                    null,
                    $model,
                    $options === [] ? null : $options
                );
            } else {
                $token = $this->getChatSettingValue('rag_chat_service_token');
                $this->chatResponder = new HttpChatResponder($endpoint, null, $token);
            }
        } catch (RuntimeException $exception) {
            $message = 'Chat responder unavailable: ' . $exception->getMessage();
            error_log($message);
            $this->lastResponderError = $message;
            $this->chatResponder = null;

            return null;
        }

        return $this->chatResponder;
    }

    private function detectEndpoint(): ?string
    {
        return $this->getChatSettingValue('rag_chat_service_url');
    }

    private function isOpenAiEndpoint(string $endpoint): bool
    {
        $driver = $this->getChatSettingValue('rag_chat_service_driver');
        if ($driver !== null) {
            $normalized = strtolower($driver);
            if ($normalized === 'openai') {
                return true;
            }
            if ($normalized !== '') {
                return false;
            }
        }

        $host = parse_url($endpoint, PHP_URL_HOST);
        if (is_string($host) && $host === 'api.openai.com') {
            return true;
        }

        $path = parse_url($endpoint, PHP_URL_PATH);
        if (is_string($path)) {
            $normalizedPath = rtrim($path, '/');
            if ($normalizedPath !== '' && str_ends_with($normalizedPath, '/v1/chat/completions')) {
                return true;
            }
        }

        if ($this->isTruthySetting('rag_chat_service_force_openai')) {
            return true;
        }

        return false;
    }

    private function normaliseOpenAiEndpoint(string $endpoint): string
    {
        $trimmed = trim($endpoint);
        if ($trimmed === '') {
            return $endpoint;
        }

        $parts = parse_url($trimmed);
        if ($parts === false) {
            return $endpoint;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return $endpoint;
        }

        $pathValue = $parts['path'] ?? null;
        $path = is_string($pathValue) ? $pathValue : '';
        $normalizedPath = $this->normaliseOpenAiPath($path);

        $userInfo = '';
        $user = $parts['user'] ?? null;
        if (is_string($user) && $user !== '') {
            $userInfo = $user;
            $pass = $parts['pass'] ?? null;
            if (is_string($pass)) {
                $userInfo .= ':' . $pass;
            }
            $userInfo .= '@';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        $rebuilt = $scheme . '://' . $userInfo . $host . $port . $normalizedPath;

        $query = $parts['query'] ?? null;
        if (is_string($query) && $query !== '') {
            $rebuilt .= '?' . $query;
        }

        $fragment = $parts['fragment'] ?? null;
        if (is_string($fragment) && $fragment !== '') {
            $rebuilt .= '#' . $fragment;
        }

        return $rebuilt;
    }

    private function normaliseOpenAiPath(string $path): string
    {
        $normalized = rtrim($path, '/');

        if ($normalized === '') {
            return '/v1/chat/completions';
        }

        if ($normalized === '/v1') {
            return '/v1/chat/completions';
        }

        if ($normalized === '/v1/models') {
            return '/v1/chat/completions';
        }

        if (str_ends_with($normalized, '/v1/chat/completions')) {
            return $normalized;
        }

        return $path === '' ? '/v1/chat/completions' : $path;
    }

    private function getChatSettingValue(string $key): ?string
    {
        $settings = $this->loadChatSettings();
        if (array_key_exists($key, $settings)) {
            $value = trim((string) $settings[$key]);

            return $value === '' ? null : $value;
        }

        $envKey = self::ENV_KEY_MAP[$key] ?? null;
        if ($envKey === null) {
            return null;
        }

        $env = getenv($envKey);
        if ($env === false) {
            return null;
        }

        $value = trim((string) $env);

        return $value === '' ? null : $value;
    }

    private function isTruthySetting(string $key): bool
    {
        $value = $this->getChatSettingValue($key);
        if ($value === null) {
            return false;
        }

        $normalized = strtolower($value);

        return $normalized !== '' && in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadChatSettings(): array
    {
        if ($this->chatSettingsLoaded) {
            return $this->chatSettings ?? [];
        }

        $this->chatSettingsLoaded = true;

        if ($this->settingsLoader !== null) {
            $data = ($this->settingsLoader)();
            if (is_array($data)) {
                /** @var array<string,mixed> $data */
                $this->chatSettings = $data;
            } else {
                $this->chatSettings = [];
            }

            return $this->chatSettings;
        }

        try {
            $pdo = Database::connectFromEnv();
            $service = new SettingsService($pdo);
            $this->chatSettings = $service->getAll();
        } catch (Throwable $exception) {
            error_log('Failed to load chat configuration: ' . $exception->getMessage());
            $this->chatSettings = [];
        }

        return $this->chatSettings;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildOpenAiOptions(): array
    {
        $options = [];

        foreach (self::OPENAI_OPTION_KEYS as $settingKey => $payloadKey) {
            $value = $this->getChatSettingValue($settingKey);
            if ($value === null) {
                continue;
            }

            $options[$payloadKey] = $value;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function buildLabel(array $metadata, string $fallback, string $locale): string
    {
        $title = isset($metadata['title']) ? trim((string) $metadata['title']) : '';
        $chunkIndex = $metadata['chunk_index'] ?? null;
        if ($title !== '') {
            if ($chunkIndex !== null && $chunkIndex !== '') {
                $sectionLabel = str_starts_with(strtolower($locale), 'en') ? 'Section' : 'Abschnitt';
                return sprintf('%s (%s %s)', $title, $sectionLabel, $chunkIndex);
            }
            return $title;
        }

        $source = isset($metadata['source']) ? trim((string) $metadata['source']) : '';
        if ($source !== '') {
            return $source;
        }

        return $fallback;
    }

    private function summariseText(string $text, int $limit = 320): string
    {
        $condensed = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($condensed === '') {
            return '';
        }

        if (mb_strlen($condensed, 'UTF-8') <= $limit) {
            return $condensed;
        }

        return mb_substr($condensed, 0, max(0, $limit - 1), 'UTF-8') . '…';
    }
}
