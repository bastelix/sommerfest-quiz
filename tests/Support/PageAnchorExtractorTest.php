<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\PageAnchorExtractor;
use PHPUnit\Framework\TestCase;

class PageAnchorExtractorTest extends TestCase
{
    private PageAnchorExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PageAnchorExtractor();
    }

    public function testExtractAnchorsWithMetaFromBlocks(): void
    {
        $content = json_encode([
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => ['headline' => 'Willkommen bei QuizRace'],
                ],
                [
                    'type' => 'feature_list',
                    'data' => ['title' => 'Alles für schnelle Live-Events'],
                ],
                [
                    'type' => 'faq',
                    'meta' => ['anchor' => 'meine-fragen'],
                    'data' => ['title' => 'Häufige Fragen'],
                ],
            ],
        ]);

        $result = $this->extractor->extractAnchorsWithMeta($content);

        self::assertCount(3, $result);

        // hero → auto-generated anchor "hero"
        self::assertSame('hero', $result[0]['anchor']);
        self::assertSame('hero', $result[0]['blockType']);
        self::assertSame('Willkommen bei QuizRace', $result[0]['blockTitle']);

        // feature_list → auto-generated anchor "feature-list"
        self::assertSame('feature-list', $result[1]['anchor']);
        self::assertSame('feature_list', $result[1]['blockType']);
        self::assertSame('Alles für schnelle Live-Events', $result[1]['blockTitle']);

        // faq → explicit anchor "meine-fragen"
        self::assertSame('meine-fragen', $result[2]['anchor']);
        self::assertSame('faq', $result[2]['blockType']);
        self::assertSame('Häufige Fragen', $result[2]['blockTitle']);
    }

    public function testDuplicateBlockTypesGetNumberedAnchors(): void
    {
        $content = json_encode([
            'blocks' => [
                ['type' => 'feature_list', 'data' => ['title' => 'Features A']],
                ['type' => 'feature_list', 'data' => ['title' => 'Features B']],
                ['type' => 'feature_list', 'data' => ['title' => 'Features C']],
            ],
        ]);

        $result = $this->extractor->extractAnchorsWithMeta($content);

        self::assertCount(3, $result);
        self::assertSame('feature-list', $result[0]['anchor']);
        self::assertSame('Features A', $result[0]['blockTitle']);
        self::assertSame('feature-list-2', $result[1]['anchor']);
        self::assertSame('Features B', $result[1]['blockTitle']);
        self::assertSame('feature-list-3', $result[2]['anchor']);
        self::assertSame('Features C', $result[2]['blockTitle']);
    }

    public function testBlockWithoutTitleReturnsEmptyString(): void
    {
        $content = json_encode([
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>Hello</p>']],
            ],
        ]);

        $result = $this->extractor->extractAnchorsWithMeta($content);

        self::assertCount(1, $result);
        self::assertSame('rich-text', $result[0]['anchor']);
        self::assertSame('rich_text', $result[0]['blockType']);
        self::assertSame('', $result[0]['blockTitle']);
    }

    public function testExtractAnchorIdsRemainsBackwardCompatible(): void
    {
        $content = json_encode([
            'blocks' => [
                ['type' => 'hero', 'data' => ['headline' => 'Test']],
                ['type' => 'faq', 'data' => ['title' => 'FAQ']],
            ],
        ]);

        $result = $this->extractor->extractAnchorIds($content);

        self::assertSame(['hero', 'faq'], $result);
    }

    public function testBlockTypeLabelReturnsKnownType(): void
    {
        self::assertSame('Hero', PageAnchorExtractor::blockTypeLabel('hero'));
        self::assertSame('Häufige Fragen', PageAnchorExtractor::blockTypeLabel('faq'));
        self::assertSame('Funktionen', PageAnchorExtractor::blockTypeLabel('feature_list'));
    }

    public function testBlockTypeLabelFallsBackForUnknownType(): void
    {
        self::assertSame('Custom block', PageAnchorExtractor::blockTypeLabel('custom_block'));
    }

    public function testHeadlineFallbackFieldPriority(): void
    {
        // "title" takes precedence over "headline"
        $content = json_encode([
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Title wins',
                        'headline' => 'Headline loses',
                    ],
                ],
            ],
        ]);

        $result = $this->extractor->extractAnchorsWithMeta($content);
        self::assertSame('Title wins', $result[0]['blockTitle']);
    }

    public function testHtmlContentReturnsEmptyMeta(): void
    {
        $content = '<div id="section-one">Hello</div><div id="section-two">World</div>';

        $result = $this->extractor->extractAnchorsWithMeta($content);

        self::assertCount(2, $result);
        self::assertSame('section-one', $result[0]['anchor']);
        self::assertSame('', $result[0]['blockType']);
        self::assertSame('', $result[0]['blockTitle']);
    }

    public function testEmptyContentReturnsEmpty(): void
    {
        self::assertSame([], $this->extractor->extractAnchorsWithMeta(''));
        self::assertSame([], $this->extractor->extractAnchorsWithMeta('{}'));
    }
}
