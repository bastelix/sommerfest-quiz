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
use function ucfirst;

final class PageAiGenerator
{
    public const ERROR_PROMPT_MISSING = 'prompt-missing';
    public const ERROR_RESPONDER_MISSING = 'responder-missing';
    public const ERROR_RESPONDER_FAILED = 'responder-failed';
    public const ERROR_EMPTY_RESPONSE = 'empty-response';
    public const ERROR_INVALID_HTML = 'invalid-html';
    public const ERROR_INVALID_JSON = 'invalid-json';

    private const SYSTEM_PROMPT = 'You are a CMS content architect. You generate block-contract-v1 JSON for marketing landing pages. Return only valid JSON, no Markdown or code fences.';

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

    private ?PageAiHtmlSanitizer $htmlSanitizer;

    private ?PageAiBlockContractValidator $blockContractValidator;

    public function __construct(
        ?RagChatService $ragChatService = null,
        ?ChatResponderInterface $chatResponder = null,
        ?string $promptTemplate = null,
        ?PageAiHtmlSanitizer $htmlSanitizer = null,
        ?PageAiBlockContractValidator $blockContractValidator = null
    ) {
        $this->ragChatService = $ragChatService ?? new RagChatService();
        $this->chatResponder = $chatResponder;
        $this->promptTemplate = $promptTemplate ?? self::PROMPT_TEMPLATE;
        $this->htmlSanitizer = $htmlSanitizer;
        $this->blockContractValidator = $blockContractValidator ?? new PageAiBlockContractValidator();
    }

    public function generate(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem,
        ?string $promptTemplate = null,
        string $namespace = ''
    ): string {
        $prompt = $this->buildPrompt($slug, $title, $theme, $colorScheme, $problem, $promptTemplate, $namespace);
        if ($prompt === '') {
            throw new RuntimeException(self::ERROR_PROMPT_MISSING);
        }

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ];
        $context = [$this->buildContext($slug, $title, $theme, $colorScheme, $namespace)];

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

        $content = $this->normaliseResponse($response);
        if ($content === '') {
            throw new RuntimeException(self::ERROR_EMPTY_RESPONSE);
        }

        return $this->validateContent($content);
    }

    private function validateContent(string $content): string
    {
        // Try block-contract JSON validation first
        if ($this->blockContractValidator !== null) {
            try {
                return $this->blockContractValidator->validate($content);
            } catch (RuntimeException) {
                // Fall through to HTML sanitization if JSON validation fails
            }
        }

        // Fall back to HTML sanitization for legacy prompt templates
        if ($this->htmlSanitizer !== null) {
            $sanitized = $this->htmlSanitizer->sanitize($content);
            if ($sanitized === '') {
                throw new RuntimeException(self::ERROR_INVALID_HTML);
            }

            return $sanitized;
        }

        throw new RuntimeException(self::ERROR_INVALID_JSON);
    }

    private function buildPrompt(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem,
        ?string $promptTemplate = null,
        string $namespace = ''
    ): string {
        $template = $this->resolvePromptTemplate($promptTemplate);
        if ($template === '') {
            return '';
        }

        $tokens = $this->resolveColorTokens($colorScheme);
        $companyName = trim($namespace) !== '' ? ucfirst(trim($namespace)) : '';

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
                '{{namespace}}',
                '{{companyName}}',
                '{{productDescription}}',
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
                trim($namespace),
                $companyName,
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
        string $colorScheme,
        string $namespace = ''
    ): array {
        $tokens = $this->resolveColorTokens($colorScheme);
        $summary = sprintf(
            'Generate block-contract-v1 JSON for slug "%s" with title "%s" (namespace: %s, theme: %s, colors: %s).',
            $slug,
            $title,
            $namespace,
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
                'namespace' => $namespace,
                'theme' => $theme,
                'color_scheme' => $colorScheme,
                'color_tokens' => $tokens,
            ],
        ];
    }

    private function normaliseResponse(string $response): string
    {
        $content = trim($response);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-z]*\s*/i', '', $content) ?? $content;
            $content = trim($content);
            $content = preg_replace('/```\s*$/', '', $content) ?? $content;
        }

        return trim($content);
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
