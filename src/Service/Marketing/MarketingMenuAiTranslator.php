<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

use function json_decode;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;

final class MarketingMenuAiTranslator
{
    public const ERROR_PROMPT_MISSING = 'prompt-missing';
    public const ERROR_RESPONDER_MISSING = 'responder-missing';
    public const ERROR_RESPONDER_FAILED = 'responder-failed';
    public const ERROR_EMPTY_RESPONSE = 'empty-response';
    public const ERROR_INVALID_JSON = 'invalid-json';
    public const ERROR_INVALID_ITEMS = 'invalid-items';

    private const SYSTEM_PROMPT = 'You translate QuizRace marketing navigation trees between locales.';

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Übersetze die folgende Navigationsstruktur in die Zielsprache.

- Behalte die Struktur und Reihenfolge unverändert.
- Übersetze nur Textfelder (label, detailTitle, detailText, detailSubline).
- Ändere Links, Icons, Layout und Positionen nicht.
- Setze das Feld "locale" jeder Zeile auf "{{targetLocale}}".
- Gib das Ergebnis als JSON unter dem Schlüssel "items" zurück.

Quellsprache: {{sourceLocale}}
Zielsprache: {{targetLocale}}
Navigation:
{{navigation}}
PROMPT;

    private RagChatService $ragChatService;

    private ?ChatResponderInterface $chatResponder;

    private string $promptTemplate;

    public function __construct(
        ?RagChatService $ragChatService = null,
        ?ChatResponderInterface $chatResponder = null,
        ?string $promptTemplate = null
    ) {
        $this->ragChatService = $ragChatService ?? new RagChatService();
        $this->chatResponder = $chatResponder;
        $this->promptTemplate = $promptTemplate ?? self::PROMPT_TEMPLATE;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function translate(array $items, string $sourceLocale, string $targetLocale): array
    {
        $prompt = $this->buildPrompt($items, $sourceLocale, $targetLocale);
        if ($prompt === '') {
            throw new RuntimeException(self::ERROR_PROMPT_MISSING);
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];
        $context = [$this->buildContext($sourceLocale, $targetLocale)];

        $responder = $this->chatResponder ?? $this->ragChatService->getChatResponder();
        if ($responder === null) {
            throw new RuntimeException(self::ERROR_RESPONDER_MISSING);
        }

        try {
            $response = $responder->respond($messages, $context);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(
                self::ERROR_RESPONDER_FAILED . ':' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $normalized = $this->normalizeResponse($response);
        if ($normalized === '') {
            throw new RuntimeException(self::ERROR_EMPTY_RESPONSE);
        }

        return $this->decodeItems($normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildPrompt(array $items, string $sourceLocale, string $targetLocale): string
    {
        $navigation = json_encode(['items' => $items], JSON_PRETTY_PRINT);
        if ($navigation === false || trim($navigation) === '') {
            return '';
        }

        return str_replace(
            ['{{sourceLocale}}', '{{targetLocale}}', '{{navigation}}'],
            [trim($sourceLocale), trim($targetLocale), $navigation],
            $this->promptTemplate
        );
    }

    /**
     * @return array{id:string,text:string,score:float,metadata:array<string, mixed>}
     */
    private function buildContext(string $sourceLocale, string $targetLocale): array
    {
        return [
            'id' => 'marketing-menu-translation',
            'text' => sprintf('Translate marketing navigation from %s to %s.', $sourceLocale, $targetLocale),
            'score' => 1.0,
            'metadata' => [
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ],
        ];
    }

    private function normalizeResponse(string $response): string
    {
        $normalized = trim($response);
        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```[a-z]*\s*/i', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/```\s*$/', '', $normalized) ?? $normalized;
        }

        return trim($normalized);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeItems(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(self::ERROR_INVALID_JSON);
        }

        $items = $decoded['items'] ?? $decoded;
        if (!is_array($items)) {
            throw new RuntimeException(self::ERROR_INVALID_ITEMS);
        }

        return $items;
    }
}
