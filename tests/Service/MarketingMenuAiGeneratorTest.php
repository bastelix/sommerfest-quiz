<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Page;
use App\Service\Marketing\MarketingMenuAiGenerator;
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

    private function createPage(string $namespace, string $slug, string $content): Page
    {
        return new Page(1, $namespace, $slug, ucfirst($slug), $content, null, null, 0, null, 'de', null, null, false);
    }
}
