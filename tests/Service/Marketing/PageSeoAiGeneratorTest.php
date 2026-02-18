<?php

declare(strict_types=1);

namespace Tests\Service\Marketing;

use App\Domain\Page;
use App\Service\Marketing\PageSeoAiGenerator;
use App\Service\RagChat\ChatResponderInterface;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\StaticChatResponder;

final class PageSeoAiGeneratorTest extends TestCase
{
    public function testDecodesSeoPayload(): void
    {
        $response = json_encode([
            'metaTitle' => 'Landing Übersicht',
            'metaDescription' => 'Kurzer Einblick in die Seite.',
            'canonicalUrl' => 'https://example.com/landing',
            'ogTitle' => 'OG Landing',
            'ogDescription' => 'OG Beschreibung',
            'robotsMeta' => 'index, follow',
        ]);

        $generator = new PageSeoAiGenerator(null, new StaticChatResponder($response), '{{title}} {{summary}}');
        $page = $this->createPage('default', 'landing', '<h1>Hero</h1><p>Kurzer Text</p>');

        $config = $generator->generate($page, 'example.com');

        $this->assertSame('Landing Übersicht', $config['metaTitle']);
        $this->assertSame('Kurzer Einblick in die Seite.', $config['metaDescription']);
        $this->assertSame('https://example.com/landing', $config['canonicalUrl']);
        $this->assertSame('OG Landing', $config['ogTitle']);
        $this->assertSame('OG Beschreibung', $config['ogDescription']);
        $this->assertSame('index, follow', $config['robotsMeta']);
        $this->assertSame($page->getId(), $config['pageId']);
    }

    public function testNormalisesResponseAndBuildsCanonicalFallback(): void
    {
        $response = "```json\n{\n  \"metaTitle\": \"Sehr langer Titel, der gekürzt werden sollte weil er eigentlich zu lang ist\",\n  \"metaDescription\": \"Beschreibung für die Seite, die möglicherweise länger als erlaubt ist und deshalb gekürzt werden muss.\",\n  \"robots\": \"index, follow\"\n}\n```";

        $generator = new PageSeoAiGenerator(null, new StaticChatResponder($response), '{{slug}}');
        $page = $this->createPage('default', 'ziel', '<p>Beschreibung des Inhalts</p>');

        $config = $generator->generate($page, 'example.org');

        $this->assertSame('ziel', $config['slug']);
        $this->assertSame('https://example.org/ziel', $config['canonicalUrl']);
        $this->assertSame('index, follow', $config['robotsMeta']);
        $this->assertLessThanOrEqual(60, mb_strlen($config['metaTitle']));
        $this->assertLessThanOrEqual(160, mb_strlen($config['metaDescription']));
    }

    public function testExtractsTextFromBlockBasedJsonContent(): void
    {
        $blockContent = json_encode([
            'blocks' => [
                [
                    'id' => 'hero',
                    'type' => 'hero',
                    'variant' => 'centered_cta',
                    'data' => [
                        'eyebrow' => 'Ohne App',
                        'headline' => 'QuizRace verbindet Teams.',
                        'subheadline' => 'Live-Ranking und Moderation.',
                        'cta' => ['primary' => ['label' => 'Demo', 'href' => '#']],
                    ],
                ],
                [
                    'id' => 'faq',
                    'type' => 'faq',
                    'variant' => 'accordion',
                    'data' => [
                        'title' => 'Häufige Fragen',
                        'items' => [
                            [
                                'id' => 'q1',
                                'question' => 'Braucht man einen Account?',
                                'answer' => 'Nein, einfach QR-Code scannen.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = json_encode([
            'metaTitle' => 'QuizRace – Live-Quiz',
            'metaDescription' => 'Teams verbinden mit Live-Ranking.',
            'canonicalUrl' => 'https://example.com/quiz',
            'ogTitle' => 'QuizRace',
            'ogDescription' => 'Live-Ranking und Moderation.',
            'robotsMeta' => 'index, follow',
        ]);

        $generator = new PageSeoAiGenerator(
            null,
            new StaticChatResponder($response),
            '{{title}} {{summary}}'
        );
        $page = $this->createPage('marketing', 'quiz', $blockContent);

        $config = $generator->generate($page, 'example.com');

        $this->assertSame('QuizRace – Live-Quiz', $config['metaTitle']);
        $this->assertSame(7, $config['pageId']);
    }

    public function testFallsBackToHtmlWhenContentIsNotJson(): void
    {
        $response = json_encode([
            'metaTitle' => 'HTML Seite',
            'metaDescription' => 'Beschreibung.',
            'robotsMeta' => 'index, follow',
        ]);

        $generator = new PageSeoAiGenerator(
            null,
            new StaticChatResponder($response),
            '{{summary}}'
        );
        $page = $this->createPage('default', 'alt', '<h1>Alte Seite</h1><p>Inhalt hier.</p>');

        $config = $generator->generate($page, 'example.com');

        $this->assertSame('HTML Seite', $config['metaTitle']);
    }

    public function testPromptContainsExtractedBlockTextNotJsonStructure(): void
    {
        $blockContent = json_encode([
            'blocks' => [
                [
                    'id' => 'hero',
                    'type' => 'hero',
                    'variant' => 'media_right',
                    'data' => [
                        'headline' => 'Teamevents leicht gemacht.',
                        'subheadline' => 'Rallye und Quiz im Browser.',
                        'cta' => ['primary' => ['label' => 'Start', 'href' => '/']],
                    ],
                ],
                [
                    'id' => 'features',
                    'type' => 'feature_list',
                    'variant' => 'icon_grid',
                    'data' => [
                        'title' => 'Was QuizRace bietet',
                        'items' => [
                            [
                                'id' => 'f1',
                                'title' => 'Live-Ranking',
                                'description' => 'Echtzeit-Punktestand für alle Teams.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $capturedPrompt = '';
        $responder = new class ($capturedPrompt) implements ChatResponderInterface {
            private string $ref;

            public function __construct(string &$ref)
            {
                $this->ref = &$ref;
            }

            public function respond(array $messages, array $context = []): string
            {
                $this->ref = $messages[1]['content'] ?? '';

                return json_encode([
                    'metaTitle' => 'Test',
                    'metaDescription' => 'Test',
                    'robotsMeta' => 'index, follow',
                ]);
            }
        };

        $generator = new PageSeoAiGenerator(null, $responder, '{{summary}}');
        $page = $this->createPage('marketing', 'test', $blockContent);
        $generator->generate($page, 'example.com');

        // Prompt must contain extracted text
        $this->assertStringContainsString('Teamevents leicht gemacht', $capturedPrompt);
        $this->assertStringContainsString('Rallye und Quiz im Browser', $capturedPrompt);
        $this->assertStringContainsString('Was QuizRace bietet', $capturedPrompt);
        $this->assertStringContainsString('Live-Ranking', $capturedPrompt);
        $this->assertStringContainsString('Echtzeit-Punktestand', $capturedPrompt);

        // Prompt must NOT contain JSON structural keys
        $this->assertStringNotContainsString('"variant"', $capturedPrompt);
        $this->assertStringNotContainsString('"media_right"', $capturedPrompt);
        $this->assertStringNotContainsString('"icon_grid"', $capturedPrompt);
        $this->assertStringNotContainsString('"type"', $capturedPrompt);
    }

    private function createPage(string $namespace, string $slug, string $content): Page
    {
        return new Page(7, $namespace, $slug, ucfirst($slug), $content, null, null, 0, null, 'de', null, null, false);
    }
}
