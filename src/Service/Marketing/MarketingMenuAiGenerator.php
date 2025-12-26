<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use DOMDocument;
use DOMNode;
use DOMXPath;
use App\Domain\Page;
use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

use function json_decode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function sprintf;
use function strip_tags;
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

    private const MAX_HTML_LENGTH = 8000;

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
                $this->prepareHtmlSnippet($page->getContent()),
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

    private function prepareHtmlSnippet(string $html): string
    {
        $cleaned = $this->stripScriptsAndStyles($html);
        $cleaned = $this->stripDataUris($cleaned);

        $structured = $this->extractStructureSummary($cleaned);
        if ($structured !== '') {
            return $this->truncate($structured, self::MAX_HTML_LENGTH);
        }

        $plain = trim(strip_tags($cleaned));
        if ($plain === '') {
            $plain = trim($cleaned);
        }

        return $this->truncate($plain, self::MAX_HTML_LENGTH);
    }

    private function stripScriptsAndStyles(string $html): string
    {
        $html = preg_replace('#<script[^>]*>.*?</script>#si', '', $html) ?? $html;

        return preg_replace('#<style[^>]*>.*?</style>#si', '', $html) ?? $html;
    }

    private function stripDataUris(string $html): string
    {
        return preg_replace('#data:[^\"\s>]+#i', '[data-uri]', $html) ?? $html;
    }

    private function extractStructureSummary(string $html): string
    {
        $document = new DOMDocument();
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }

        if ($loaded === false) {
            return '';
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//h1 | //h2 | //h3 | //section | //article | //a[@href]');
        if ($nodes === false) {
            return '';
        }

        $lines = [];
        /** @var DOMNode $node */
        foreach ($nodes as $node) {
            $text = trim($node->textContent ?? '');
            if ($text === '') {
                continue;
            }

            $id = '';
            if ($node->attributes !== null) {
                $idAttribute = $node->attributes->getNamedItem('id');
                if ($idAttribute !== null) {
                    $id = trim((string) $idAttribute->nodeValue);
                }
            }

            if ($id === '' && $node->nodeName === 'a' && $node->attributes !== null) {
                $hrefAttribute = $node->attributes->getNamedItem('href');
                $href = $hrefAttribute !== null ? trim((string) $hrefAttribute->nodeValue) : '';
                if ($href !== '') {
                    $id = $href;
                }
            }

            $label = strtoupper($node->nodeName);
            $lines[] = $id !== ''
                ? sprintf('%s: %s (%s)', $label, $text, $id)
                : sprintf('%s: %s', $label, $text);

            if (mb_strlen(implode("\n", $lines)) >= self::MAX_HTML_LENGTH) {
                break;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function truncate(string $content, int $maxLength): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        return trim(mb_substr($content, 0, $maxLength)) . ' … [truncated]';
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
