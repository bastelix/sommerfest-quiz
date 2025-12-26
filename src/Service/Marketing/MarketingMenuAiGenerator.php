<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Domain\Page;
use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

use function json_decode;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;

final class MarketingMenuAiGenerator
{
    public const ERROR_PROMPT_MISSING = 'prompt-missing';
    public const ERROR_RESPONDER_MISSING = 'responder-missing';
    public const ERROR_RESPONDER_FAILED = 'responder-failed';
    public const ERROR_EMPTY_RESPONSE = 'empty-response';
    public const ERROR_INVALID_JSON = 'invalid-json';
    public const ERROR_INVALID_ITEMS = 'invalid-items';

    private const SYSTEM_PROMPT = 'You turn QuizRace marketing page HTML into structured navigation menus.';

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Nutze den folgenden HTML-Inhalt einer Marketing-Seite, um Navigationspunkte zu erzeugen.

- Ziehe H1/H2-Überschriften, Abschnittstitel und Anker-IDs (#id) heran, um Labels und Links zu bauen.
- Hauptbereiche werden zu Haupteinträgen. Unterüberschriften oder Listenpunkte innerhalb eines Bereichs werden
  zu children des jeweiligen Haupteintrags.
- Links sollen relative Anker (#faq), bestehende Hrefs im Dokument oder Slug-basierte Pfade (/{{slug}}#section)
  verwenden. Erfinde keine externen Links.
- layout: "dropdown" wenn children existieren, sonst "link". Weitere Layouts nicht nutzen.
- isActive: true, außer der Abschnitt ist explizit als ausgeblendet gekennzeichnet.
- Gib das Ergebnis als JSON-Array unter dem Schlüssel "items" zurück (keine Markdown-Fences).

Kontext:
Slug: {{slug}}
Locale: {{locale}}
Titel: {{title}}

HTML:
{{html}}
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
     * @return array<int, array<string, mixed>>
     */
    public function generate(Page $page, ?string $locale = null, ?string $promptTemplate = null): array
    {
        $prompt = $this->buildPrompt($page, $locale, $promptTemplate);
        if ($prompt === '') {
            throw new RuntimeException(self::ERROR_PROMPT_MISSING);
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];
        $context = [$this->buildContext($page, $locale)];

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

    private function buildPrompt(Page $page, ?string $locale, ?string $promptTemplate = null): string
    {
        $template = $this->resolvePromptTemplate($promptTemplate);
        if ($template === '') {
            return '';
        }

        return str_replace(
            ['{{slug}}', '{{locale}}', '{{title}}', '{{html}}'],
            [
                trim($page->getSlug()),
                trim($locale ?? $page->getLanguage() ?? 'de'),
                trim($page->getTitle()),
                trim($page->getContent()),
            ],
            $template
        );
    }

    private function resolvePromptTemplate(?string $promptTemplate): string
    {
        $candidate = $promptTemplate !== null ? trim($promptTemplate) : '';
        if ($candidate !== '') {
            return $candidate;
        }

        return trim($this->promptTemplate);
    }

    /**
     * @return array{id:string,text:string,score:float,metadata:array<string,mixed>}
     */
    private function buildContext(Page $page, ?string $locale): array
    {
        $summary = sprintf(
            'Generate navigation for slug "%s" (locale: %s, namespace: %s).',
            $page->getSlug(),
            $locale ?? $page->getLanguage() ?? 'de',
            $page->getNamespace()
        );

        return [
            'id' => 'marketing-menu-request',
            'text' => $summary,
            'score' => 1.0,
            'metadata' => [
                'page_id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'locale' => $locale ?? $page->getLanguage(),
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
