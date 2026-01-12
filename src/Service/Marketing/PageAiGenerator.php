<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

use function array_map;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;

final class PageAiGenerator
{
    public const ERROR_PROMPT_MISSING = 'prompt-missing';
    public const ERROR_RESPONDER_MISSING = 'responder-missing';
    public const ERROR_RESPONDER_FAILED = 'responder-failed';
    public const ERROR_EMPTY_RESPONSE = 'empty-response';
    public const ERROR_INVALID_HTML = 'invalid-html';

    private const SYSTEM_PROMPT = 'You generate clean UIkit HTML for QuizRace marketing pages.';

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Create German UIkit HTML for a marketing landing page. The HTML is injected into the content block of
templates/marketing/default.twig (header/footer already exist). Base the structure on the landing layout:
- The layout consists of multiple <section> blocks using uk-section and nested uk-container.
- Provide a hero section with headline, lead text, and primary call-to-action button.
- Add sections with the ids: innovations, how-it-works, scenarios, pricing, faq, contact-us (use when relevant).
- Use uk-grid, uk-card, uk-list, uk-accordion, and uk-button classes for layout and components.
- Use only UIkit classes (classes must start with "uk-").
- Use the provided color tokens. Map them to UIkit utility classes or CSS variables on a top-level wrapper:
  Primary => --qr-landing-primary, Background => --qr-landing-bg, Accent => --qr-landing-accent.
- Keep copy concise, benefit-focused, and in German.
- Do not include <html>, <head>, <body>, <script>, or <style> tags.
- Return only HTML, no Markdown or code fences.

Inputs:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Color tokens: Primary={{primaryColor}}, Background={{backgroundColor}}, Accent={{accentColor}}
Problem to address: {{problem}}
PROMPT;

    private const DEFAULT_COLOR_TOKENS = [
        'primary' => '#1e87f0',
        'background' => '#0f172a',
        'accent' => '#f59e0b',
    ];

    private RagChatService $ragChatService;

    private ?ChatResponderInterface $chatResponder;

    private string $promptTemplate;

    private PageAiHtmlSanitizer $htmlSanitizer;

    public function __construct(
        ?RagChatService $ragChatService = null,
        ?ChatResponderInterface $chatResponder = null,
        ?string $promptTemplate = null,
        ?PageAiHtmlSanitizer $htmlSanitizer = null
    ) {
        $this->ragChatService = $ragChatService ?? new RagChatService();
        $this->chatResponder = $chatResponder;
        $this->promptTemplate = $promptTemplate ?? self::PROMPT_TEMPLATE;
        $this->htmlSanitizer = $htmlSanitizer ?? new PageAiHtmlSanitizer();
    }

    public function generate(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem,
        ?string $promptTemplate = null
    ): string {
        $prompt = $this->buildPrompt($slug, $title, $theme, $colorScheme, $problem, $promptTemplate);
        if ($prompt === '') {
            throw new RuntimeException(self::ERROR_PROMPT_MISSING);
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];
        $context = [$this->buildContext($slug, $title, $theme, $colorScheme)];

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

        $html = $this->normaliseHtml($response);
        if ($html === '') {
            throw new RuntimeException(self::ERROR_EMPTY_RESPONSE);
        }

        $sanitized = $this->htmlSanitizer->sanitize($html);
        if ($sanitized === '') {
            throw new RuntimeException(self::ERROR_INVALID_HTML);
        }

        return $sanitized;
    }

    private function buildPrompt(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem,
        ?string $promptTemplate = null
    ): string {
        $template = $this->resolvePromptTemplate($promptTemplate);
        if ($template === '') {
            return '';
        }

        $tokens = $this->resolveColorTokens($colorScheme);

        return str_replace(
            [
                '{{slug}}',
                '{{title}}',
                '{{theme}}',
                '{{colorScheme}}',
                '{{primaryColor}}',
                '{{backgroundColor}}',
                '{{accentColor}}',
                '{{problem}}',
            ],
            [
                trim($slug),
                trim($title),
                trim($theme),
                trim($colorScheme),
                $tokens['primary'],
                $tokens['background'],
                $tokens['accent'],
                trim($problem),
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
    private function buildContext(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme
    ): array {
        $tokens = $this->resolveColorTokens($colorScheme);
        $summary = sprintf(
            'Generate UIkit marketing HTML for slug "%s" with title "%s" (theme: %s, colors: %s).',
            $slug,
            $title,
            $theme,
            $colorScheme
        );

        return [
            'id' => 'marketing-page-request',
            'text' => $summary,
            'score' => 1.0,
            'metadata' => [
                'slug' => $slug,
                'title' => $title,
                'theme' => $theme,
                'color_scheme' => $colorScheme,
                'color_tokens' => $tokens,
            ],
        ];
    }

    private function normaliseHtml(string $response): string
    {
        $html = trim($response);

        if (str_starts_with($html, '```')) {
            $html = preg_replace('/^```[a-z]*\s*/i', '', $html) ?? $html;
            $html = trim($html);
            $html = preg_replace('/```\s*$/', '', $html) ?? $html;
        }

        return trim($html);
    }

    /**
     * @return array{primary:string,background:string,accent:string}
     */
    private function resolveColorTokens(string $colorScheme): array
    {
        $tokens = self::DEFAULT_COLOR_TOKENS;
        $normalized = trim($colorScheme);
        if ($normalized === '') {
            return $tokens;
        }

        $tokens['primary'] = $this->extractColorToken($normalized, ['primary', 'primär', 'primaer']) ?? $tokens['primary'];
        $tokens['background'] = $this->extractColorToken(
            $normalized,
            ['background', 'hintergrund', 'bg']
        ) ?? $tokens['background'];
        $tokens['accent'] = $this->extractColorToken($normalized, ['accent', 'akzent']) ?? $tokens['accent'];

        return $tokens;
    }

    /**
     * @param list<string> $labels
     */
    private function extractColorToken(string $colorScheme, array $labels): ?string
    {
        $pattern = sprintf('/(?:%s)\s*[:=]\s*([^,;]+)/i', implode('|', array_map('preg_quote', $labels)));
        if (preg_match($pattern, $colorScheme, $matches) !== 1) {
            return null;
        }
        $candidate = trim($matches[1]);
        return $candidate !== '' ? $candidate : null;
    }
}
