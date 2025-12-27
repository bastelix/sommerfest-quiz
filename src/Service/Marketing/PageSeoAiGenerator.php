<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Domain\Page;
use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

use function is_array;
use function json_decode;
use function mb_substr;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function sprintf;
use function trim;

final class PageSeoAiGenerator
{
    public const ERROR_PROMPT_MISSING = 'prompt-missing';
    public const ERROR_RESPONDER_MISSING = 'responder-missing';
    public const ERROR_RESPONDER_FAILED = 'responder-failed';
    public const ERROR_EMPTY_RESPONSE = 'empty-response';
    public const ERROR_INVALID_JSON = 'invalid-json';

    private const MAX_TITLE_LENGTH = 60;
    private const MAX_DESCRIPTION_LENGTH = 160;

    private const SYSTEM_PROMPT = 'You generate concise German SEO metadata for QuizRace marketing pages.';

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Erstelle deutschsprachige SEO-Metadaten für eine Marketing-Seite. Antworte ausschließlich mit einem JSON-Objekt
(ohne Markdown-Codeblock) mit diesen Schlüsseln: metaTitle, metaDescription, ogTitle, ogDescription, canonicalUrl,
robotsMeta.

Vorgaben:
- metaTitle <= 60 Zeichen, metaDescription <= 160 Zeichen.
- ogTitle und ogDescription sollen zur Meta-Variante passen (keine HTML-Tags).
- canonicalUrl nutzt die Domain und den Slug: {{baseUrl}}{{slugPath}}.
- robotsMeta standardmäßig "index, follow".
- Verwende den bereitgestellten Slug unverändert.
- Schreibe prägnant, aktiv und vorteilsorientiert.

Basisdaten:
- Titel: {{title}}
- Slug: {{slug}}
- Domain: {{domain}}
- Basis-URL: {{baseUrl}}
- Seiteninhalt (gekürzt): {{summary}}
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
     * @return array{
     *     pageId:int,
     *     slug:string,
     *     domain:?string,
     *     metaTitle:string,
     *     metaDescription:string,
     *     canonicalUrl:?string,
     *     robotsMeta:string,
     *     ogTitle:string,
     *     ogDescription:string
     * }
     */
    public function generate(Page $page, ?string $domain = null, ?string $promptTemplate = null): array
    {
        $normalizedDomain = trim((string) $domain);
        $prompt = $this->buildPrompt($page, $normalizedDomain, $promptTemplate);
        if ($prompt === '') {
            throw new RuntimeException(self::ERROR_PROMPT_MISSING);
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];
        $context = [$this->buildContext($page, $normalizedDomain)];

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

        return $this->decodeConfig($normalized, $page, $normalizedDomain);
    }

    private function buildPrompt(Page $page, string $domain, ?string $promptTemplate = null): string
    {
        $template = $this->resolvePromptTemplate($promptTemplate);
        if ($template === '') {
            return '';
        }

        $baseUrl = $domain !== '' ? 'https://' . $domain : '';
        $slugPath = '/' . ltrim($page->getSlug(), '/');

        return str_replace(
            ['{{title}}', '{{slug}}', '{{domain}}', '{{baseUrl}}', '{{summary}}', '{{slugPath}}'],
            [
                trim($page->getTitle()),
                trim($page->getSlug()),
                $domain,
                $baseUrl,
                $this->summariseContent($page->getContent()),
                $slugPath,
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
    private function buildContext(Page $page, string $domain): array
    {
        $baseUrl = $domain !== '' ? 'https://' . $domain : '';

        return [
            'id' => 'marketing-seo-request',
            'text' => trim(
                sprintf(
                    'Generate SEO metadata for "%s" (%s). Canonical base: %s',
                    $page->getTitle(),
                    $page->getSlug(),
                    $baseUrl !== '' ? $baseUrl : 'no-domain'
                )
            ),
            'score' => 1.0,
            'metadata' => [
                'page_id' => $page->getId(),
                'namespace' => $page->getNamespace(),
                'slug' => $page->getSlug(),
                'domain' => $domain,
            ],
        ];
    }

    private function summariseContent(string $html): string
    {
        $stripped = strip_tags($html);
        $normalized = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return trim(mb_substr(trim($normalized), 0, 1200));
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
     * @return array{
     *     pageId:int,
     *     slug:string,
     *     domain:?string,
     *     metaTitle:string,
     *     metaDescription:string,
     *     canonicalUrl:?string,
     *     robotsMeta:string,
     *     ogTitle:string,
     *     ogDescription:string
     * }
     */
    private function decodeConfig(string $response, Page $page, string $domain): array
    {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(self::ERROR_INVALID_JSON);
        }

        $payload = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;

        $metaTitle = $this->sanitizeText($payload['metaTitle'] ?? $payload['meta_title'] ?? '', self::MAX_TITLE_LENGTH);
        $metaDescription = $this->sanitizeText(
            $payload['metaDescription'] ?? $payload['meta_description'] ?? '',
            self::MAX_DESCRIPTION_LENGTH
        );
        $ogTitle = $this->sanitizeText($payload['ogTitle'] ?? $payload['og_title'] ?? $metaTitle, self::MAX_TITLE_LENGTH);
        $ogDescription = $this->sanitizeText(
            $payload['ogDescription'] ?? $payload['og_description'] ?? $metaDescription,
            self::MAX_DESCRIPTION_LENGTH
        );

        $canonicalCandidate = isset($payload['canonicalUrl'])
            ? (string) $payload['canonicalUrl']
            : (isset($payload['canonical']) ? (string) $payload['canonical'] : '');

        $robotsMeta = $this->sanitizeText($payload['robotsMeta'] ?? $payload['robots'] ?? 'index, follow', 50);

        return [
            'pageId' => $page->getId(),
            'slug' => $page->getSlug(),
            'domain' => $domain !== '' ? $domain : null,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $this->buildCanonical($domain, $page->getSlug(), $canonicalCandidate),
            'robotsMeta' => $robotsMeta !== '' ? $robotsMeta : 'index, follow',
            'ogTitle' => $ogTitle !== '' ? $ogTitle : $metaTitle,
            'ogDescription' => $ogDescription !== '' ? $ogDescription : $metaDescription,
        ];
    }

    private function sanitizeText(?string $value, int $maxLength): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $stripped = strip_tags($normalized);
        $collapsed = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;

        return trim(mb_substr($collapsed, 0, $maxLength));
    }

    private function buildCanonical(string $domain, string $slug, string $candidate): ?string
    {
        $trimmed = trim($candidate);
        if ($trimmed !== '') {
            return $trimmed;
        }

        if ($domain === '') {
            return null;
        }

        $path = '/' . ltrim($slug, '/');

        return 'https://' . $domain . $path;
    }
}
