<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use App\Service\RagChat\ChatResponderInterface;
use App\Service\RagChat\RagChatService;
use RuntimeException;

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

    private const SYSTEM_PROMPT = 'You generate clean UIkit HTML for QuizRace marketing pages.';

    private const PROMPT_TEMPLATE = <<<'PROMPT'
Create German UIkit HTML for a marketing landing page. The HTML is injected into the content block of
templates/marketing/landing.twig (header/footer already exist). Base the structure on our UIkit-based landing layout:
- Use multiple <section> blocks with uk-section and uk-container.
- Provide a hero section with headline, lead text, and primary call-to-action.
- Add sections with the ids: innovations, how-it-works, scenarios, pricing, faq, contact-us (use when relevant).
- Use uk-grid, uk-card, uk-list, uk-accordion, and uk-button classes for layout and components.
- Keep copy concise, benefit-focused, and in German.
- Do not include <html>, <head>, <body>, <script>, or <style> tags.
- Return only HTML, no Markdown or code fences.

Inputs:
Slug: {{slug}}
Title: {{title}}
Theme: {{theme}}
Color scheme: {{colorScheme}}
Problem to address: {{problem}}
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

    public function generate(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem
    ): string {
        $prompt = $this->buildPrompt($slug, $title, $theme, $colorScheme, $problem);
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

        return $html;
    }

    private function buildPrompt(
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem
    ): string {
        $template = trim($this->promptTemplate);
        if ($template === '') {
            return '';
        }

        return str_replace(
            ['{{slug}}', '{{title}}', '{{theme}}', '{{colorScheme}}', '{{problem}}'],
            [trim($slug), trim($title), trim($theme), trim($colorScheme), trim($problem)],
            $template
        );
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
}
