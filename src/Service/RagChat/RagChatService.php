<?php

declare(strict_types=1);

namespace App\Service\RagChat;

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

    public function __construct(?string $indexPath = null)
    {
        $basePath = dirname(__DIR__, 3);
        $this->indexPath = $indexPath ?? $basePath . '/data/rag-chatbot/index.json';
    }

    public function answer(string $question, string $locale = self::DEFAULT_LOCALE): RagChatResponse
    {
        $question = trim($question);
        if ($question === '') {
            throw new RuntimeException('Question must not be empty.');
        }

        $index = SemanticIndex::load($this->indexPath);
        $results = $index->search($question, 4, 0.05);
        $messages = self::MESSAGE_TEMPLATES[$locale] ?? self::MESSAGE_TEMPLATES[self::DEFAULT_LOCALE];

        $contextItems = [];
        foreach ($results as $result) {
            $contextItems[] = $this->buildContextItem($result, $locale);
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

    private function buildContextItem(SearchResult $result, string $locale): RagChatContextItem
    {
        $metadata = $result->getMetadata();
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
