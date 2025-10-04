<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Support\DomainNameHelper;
use RuntimeException;

use function getenv;
use function is_string;
use function parse_url;
use function trim;

/**
 * High-level facade that prepares responses for the marketing chat endpoint.
 */
final class RagChatService
{
    private const MESSAGE_TEMPLATES = [
        'de' => [
            'intro' => 'Basierend auf der Wissensbasis habe ich folgende Hinweise gefunden:',
            'no_results' => 'Ich konnte keine passenden Informationen in der Dokumentation finden. Bitte formuliere deine Frage anders oder schränke das Thema ein.',
            'question' => 'Frage',
        ],
        'en' => [
            'intro' => 'Based on our knowledge base I found the following hints:',
            'no_results' => 'I could not find matching information in the documentation. Please rephrase your question or narrow down the topic.',
            'question' => 'Question',
        ],
    ];

    private const SYSTEM_PROMPTS = [
        'de' => 'Du bist ein hilfreicher Assistent für die QuizRace-Dokumentation. Beantworte Fragen ausschließlich anhand der bereitgestellten Kontexte.',
        'en' => 'You are a helpful assistant for the QuizRace documentation. Answer questions only by relying on the supplied context snippets.',
    ];

    private const CONTEXT_HEADERS = [
        'de' => "Kontext aus der Wissensbasis:\n",
        'en' => "Context from the knowledge base:\n",
    ];

    private const DEFAULT_LOCALE = 'de';

    private string $indexPath;

    private string $domainIndexBase;

    private ?ChatResponderInterface $chatResponder;

    public function __construct(?string $indexPath = null, ?string $domainIndexBase = null, ?ChatResponderInterface $chatResponder = null)
    {
        $basePath = dirname(__DIR__, 3);
        $this->indexPath = $indexPath ?? $basePath . '/data/rag-chatbot/index.json';
        $this->domainIndexBase = $domainIndexBase ?? $basePath . '/data/rag-chatbot/domains';
        $this->chatResponder = $chatResponder;
    }

    public function answer(string $question, string $locale = self::DEFAULT_LOCALE, ?string $domain = null): RagChatResponse
    {
        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('Question must not be empty.');
        }

        $globalIndex = SemanticIndex::load($this->indexPath);
        $contextResults = [];
        $seenChunks = [];

        $normalizedDomain = $domain !== null ? DomainNameHelper::normalize($domain) : '';
        if ($normalizedDomain !== '') {
            $domainIndexPath = $this->domainIndexBase . '/' . $normalizedDomain . '/index.json';
            if (is_file($domainIndexPath)) {
                try {
                    $domainIndex = SemanticIndex::load($domainIndexPath);
                    foreach ($domainIndex->search($question, 4, 0.05) as $result) {
                        $contextResults[] = ['result' => $result, 'domain' => $normalizedDomain];
                        $seenChunks[$result->getChunkId()] = true;
                    }
                } catch (RuntimeException $exception) {
                    error_log('Failed to load domain-specific RAG index: ' . $exception->getMessage());
                }
            }
        }

        foreach ($globalIndex->search($question, 4, 0.05) as $result) {
            if (isset($seenChunks[$result->getChunkId()])) {
                continue;
            }
            $contextResults[] = ['result' => $result, 'domain' => null];
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
            $answer = $this->composeFallbackAnswer($question, $contextItems, $messages);
        }

        return new RagChatResponse($question, $answer, $contextItems);
    }

    /**
     * @param array{intro:string,no_results:string,question:string} $messages
     * @param list<RagChatContextItem> $context
     */
    private function composeFallbackAnswer(string $question, array $context, array $messages): string
    {
        $lines = [$messages['intro']];
        foreach ($context as $index => $item) {
            $number = $index + 1;
            $lines[] = sprintf('%d. %s: %s', $number, $item->getLabel(), $item->getSnippet());
        }
        $lines[] = '';
        $lines[] = sprintf('%s: %s', $messages['question'], $question);

        return implode("\n", $lines);
    }

    /**
     * @return array{item:RagChatContextItem,payload:array{id:string,text:string,score:float,metadata:array<string,mixed>}}
     */
    private function buildContextEntry(SearchResult $result, string $locale, ?string $originDomain = null): array
    {
        $metadata = $result->getMetadata();
        if ($originDomain !== null && $originDomain !== '') {
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
        $responder = $this->chatResponder ?? $this->createDefaultResponder();
        if ($responder === null) {
            return null;
        }

        try {
            return $responder->respond($messages, $context);
        } catch (RuntimeException $exception) {
            error_log('Chat responder failed: ' . $exception->getMessage());
        }

        return null;
    }

    private function createDefaultResponder(): ?ChatResponderInterface
    {
        try {
            $endpoint = $this->detectEndpoint();
            if ($endpoint !== null && $this->isOpenAiEndpoint($endpoint)) {
                $this->chatResponder = new OpenAiChatResponder($endpoint);
            } else {
                $this->chatResponder = new HttpChatResponder($endpoint);
            }
        } catch (RuntimeException $exception) {
            error_log('Chat responder unavailable: ' . $exception->getMessage());
            $this->chatResponder = null;

            return null;
        }

        return $this->chatResponder;
    }

    private function detectEndpoint(): ?string
    {
        $value = getenv('RAG_CHAT_SERVICE_URL');
        if ($value === false) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isOpenAiEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (!is_string($host)) {
            return false;
        }

        return $host === 'api.openai.com';
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
