<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Page;
use App\Service\Marketing\MarketingMenuAiGenerator;
use App\Service\RagChat\ChatResponderInterface;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\StaticChatResponder;

final class MarketingMenuAiGeneratorTest extends TestCase
{
    public function testDecodesMenuItemsFromResponder(): void
    {
        $responder = new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Intro', 'href' => '#intro', 'layout' => 'link', 'isActive' => true],
            ],
        ]));

        $generator = new MarketingMenuAiGenerator(null, $responder, '{{slug}} {{html}}');
        $page = $this->createPage('default', 'landing', '<h1 id="intro">Intro</h1>');

        $items = $generator->generate($page, 'de');

        $this->assertCount(1, $items);
        $this->assertSame('Intro', $items[0]['label']);
        $this->assertSame('#intro', $items[0]['href']);
    }

    public function testStripsCodeFencesAndValidatesJson(): void
    {
        $response = "```json\n{\"items\": [{\"label\": \"FAQ\", \"href\": \"#faq\"}]}\n```";
        $responder = new StaticChatResponder($response);
        $generator = new MarketingMenuAiGenerator(null, $responder, '{{slug}}');
        $page = $this->createPage('default', 'faq', '<h2 id="faq">FAQ</h2>');

        $items = $generator->generate($page, 'de');

        $this->assertSame('#faq', $items[0]['href']);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder('invalid-json'), '{{slug}}');
        $page = $this->createPage('default', 'broken', '<h1>Broken</h1>');

        $this->expectExceptionMessage(MarketingMenuAiGenerator::ERROR_INVALID_JSON);

        $generator->generate($page, 'de');
    }

    public function testStripsLargeDataUrisAndScriptsFromPrompt(): void
    {
        $capturingResponder = new class implements ChatResponderInterface {
            public array $messages = [];

            public function respond(array $messages, array $context = []): string
            {
                $this->messages = $messages;

                return json_encode(['items' => []]);
            }
        };

        $generator = new MarketingMenuAiGenerator(null, $capturingResponder, '{{html}}');
        $image = '<img src="data:image/png;base64,' . str_repeat('A', 5000) . '">';
        $script = '<script>console.log("should be removed");</script>';
        $page = $this->createPage('default', 'hero', '<h1 id="hero">Hero</h1>' . $image . $script . '<h2 id="faq">FAQ</h2>');

        $generator->generate($page, 'de');

        $prompt = $capturingResponder->messages[1]['content'] ?? '';
        $this->assertStringContainsString('H1: Hero (hero)', $prompt);
        $this->assertStringContainsString('H2: FAQ (faq)', $prompt);
        $this->assertStringNotContainsString('data:image/png', $prompt);
        $this->assertStringNotContainsString('script', $prompt);
        $this->assertLessThan(9000, strlen($prompt));
    }

    public function testFallsBackOnUnknownAnchors(): void
    {
        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Invalid', 'href' => '#missing', 'layout' => 'link'],
            ],
        ])), '{{slug}}');
        $page = $this->createPage('default', 'landing', '<h1 id="intro">Intro</h1>');

        $items = $generator->generate($page, 'de');

        $this->assertSame('/landing', $items[0]['href']);
    }

    public function testIgnoresInvalidLinksAndKeepsChildren(): void
    {
        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                [
                    'label' => 'Broken parent',
                    'href' => '#unknown',
                    'layout' => 'dropdown',
                    'children' => [
                        ['label' => 'Intro', 'href' => '#intro', 'layout' => 'link'],
                    ],
                ],
                ['label' => 'FAQ', 'href' => '#faq', 'layout' => 'link'],
            ],
        ])), '{{slug}}');

        $page = $this->createPage('default', '', '<h1 id="intro">Intro</h1><h2 id="faq">FAQ</h2>');

        $items = $generator->generate($page, 'de');

        $this->assertSame('#intro', $items[0]['href']);
        $this->assertSame('#faq', $items[1]['href']);
    }

    public function testProcessesMixedValidAndInvalidLinks(): void
    {
        $generator = new MarketingMenuAiGenerator(null, new StaticChatResponder(json_encode([
            'items' => [
                ['label' => 'Intro', 'href' => '#intro', 'layout' => 'link'],
                ['label' => 'Empty', 'href' => '   ', 'layout' => 'link'],
                ['label' => 'Unknown', 'href' => '/other#missing', 'layout' => 'link'],
            ],
        ])), '{{slug}}');

        $page = $this->createPage('default', 'landing', '<h1 id="intro">Intro</h1>');

        $items = $generator->generate($page, 'de');

        $this->assertSame('#intro', $items[0]['href']);
        $this->assertSame('/landing', $items[1]['href']);
        $this->assertSame('/landing', $items[2]['href']);
    }

    private function createPage(string $namespace, string $slug, string $content): Page
    {
        return new Page(1, $namespace, $slug, ucfirst($slug), $content, null, null, 0, null, 'de', null, null, false);
    }
}
