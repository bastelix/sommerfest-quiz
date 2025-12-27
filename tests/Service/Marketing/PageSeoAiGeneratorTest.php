<?php

declare(strict_types=1);

namespace Tests\Service\Marketing;

use App\Domain\Page;
use App\Service\Marketing\PageSeoAiGenerator;
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

    private function createPage(string $namespace, string $slug, string $content): Page
    {
        return new Page(7, $namespace, $slug, ucfirst($slug), $content, null, null, 0, null, 'de', null, null, false);
    }
}
