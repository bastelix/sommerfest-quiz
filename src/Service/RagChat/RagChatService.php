<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Support\DomainNameHelper;
use RuntimeException;

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

    private const DEFAULT_LOCALE = 'de';

    private string $indexPath;

    private string $domainIndexBase;

    public function __construct(?string $indexPath = null, ?string $domainIndexBase = null)
    {
        $basePath = dirname(__DIR__, 3);
        $this->indexPath = $indexPath ?? $basePath . '/data/rag-chatbot/index.json';
        $this->domainIndexBase = $domainIndexBase ?? $basePath . '/data/rag-chatbot/domains';
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

        $contextItems = [];
        foreach ($contextResults as $entry) {
            $contextItems[] = $this->buildContextItem($entry['result'], $locale, $entry['domain']);
        }

        $answer = $this->composeAnswer($question, $contextItems, $messages);

        return new RagChatResponse($question, $answer, $contextItems);
    }

    /**
     * @param array{intro:string,no_results:string,question:string} $messages
     * @param list<RagChatContextItem> $context
     */
    private function composeAnswer(string $question, array $context, array $messages): string
    {
        if ($context === []) {
            return $messages['no_results'];
        }

        $lines = [$messages['intro']];
        foreach ($context as $index => $item) {
            $number = $index + 1;
            $lines[] = sprintf('%d. %s: %s', $number, $item->getLabel(), $item->getSnippet());
        }
        $lines[] = '';
        $lines[] = sprintf('%s: %s', $messages['question'], $question);

        return implode("\n", $lines);
    }

    private function buildContextItem(SearchResult $result, string $locale, ?string $originDomain = null): RagChatContextItem
    {
        $metadata = $result->getMetadata();
        if ($originDomain !== null && $originDomain !== '') {
            $metadata['domain'] = $originDomain;
        }
        $label = $this->buildLabel($metadata, $result->getChunkId(), $locale);
        $snippet = $this->summariseText($result->getText());

        return new RagChatContextItem($label, $snippet, round($result->getScore(), 4), $metadata);
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
