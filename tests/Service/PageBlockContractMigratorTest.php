<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Page;
use App\Service\PageBlockContractMigrator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\FakePageService;

final class PageBlockContractMigratorTest extends TestCase
{
    public function testMigratesLegacyBlocksAndPersists(): void
    {
        $legacyContent = json_encode([
            'blocks' => [
                [
                    'id' => 'hero-1',
                    'type' => 'hero',
                    'data' => [
                        'headline' => 'Hello',
                        'subheadline' => 'World',
                        'cta' => ['label' => 'Go', 'href' => '/next'],
                        'layout' => 'media-right',
                    ],
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'data' => ['body' => '<p>Copy</p>', 'alignment' => 'center'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(1, 'default', 'landing', $legacyContent);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator(
            $fakePages,
            $schema,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2024-01-01T00:00:00Z')
        );

        $report = $migrator->migrateAll();

        self::assertSame(1, $report['processed']);
        self::assertSame(1, $report['migrated']);
        self::assertSame(0, $report['errors']['total']);
        self::assertCount(1, $fakePages->savedContent);

        $saved = json_decode($fakePages->savedContent[0]['content'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('block-contract-v1', $saved['meta']['migrationVersion']);
        self::assertSame('2024-01-01T00:00:00+00:00', $saved['meta']['migratedAt']);

        $hero = $saved['blocks'][0];
        self::assertSame('hero', $hero['type']);
        self::assertSame('media_right', $hero['variant']);
        self::assertArrayNotHasKey('layout', $hero['data']);

        $richText = $saved['blocks'][1];
        self::assertSame('rich_text', $richText['type']);
        self::assertSame('prose', $richText['variant']);
        self::assertSame('center', $richText['data']['alignment']);
    }

    public function testReportsUnknownBlockTypes(): void
    {
        $content = json_encode([
            'blocks' => [
                ['id' => 'bad', 'type' => 'unsupported', 'data' => []],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(2, 'default', 'unknown', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(0, $report['migrated']);
        self::assertSame(1, $report['errors']['total']);
        self::assertSame(1, $report['errors']['unknown_block_type']);
        self::assertEmpty($fakePages->savedContent);
    }

    public function testNormalizesLegacyCtaBlocks(): void
    {
        $content = json_encode([
            'blocks' => [
                [
                    'id' => 'cta-1',
                    'type' => 'cta',
                    'variant' => 'split',
                    'data' => ['label' => 'Go', 'href' => '/cta'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(5, 'default', 'cta', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(1, $report['migrated']);
        self::assertCount(1, $fakePages->savedContent);

        $saved = json_decode($fakePages->savedContent[0]['content'], true, 512, JSON_THROW_ON_ERROR);
        $ctaBlock = $saved['blocks'][0];

        self::assertSame('cta', $ctaBlock['type']);
        self::assertSame('split', $ctaBlock['variant']);
        self::assertSame(
            ['primary' => ['label' => 'Go', 'href' => '/cta']],
            $ctaBlock['data']
        );
    }

    public function testSkipsAlreadyMigratedContent(): void
    {
        $content = json_encode([
            'meta' => [
                'migrationVersion' => 'block-contract-v1',
                'migratedAt' => '2024-01-01T00:00:00+00:00',
                'annotations' => ['semanticSplit' => true, 'reviewed' => true],
            ],
            'blocks' => [
                [
                    'id' => 'rich',
                    'type' => 'rich_text',
                    'variant' => 'prose',
                    'data' => ['body' => '<p>Ok</p>', 'alignment' => 'start'],
                    'tokens' => ['spacing' => 'normal', 'width' => 'normal'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(3, 'default', 'already', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(0, $report['migrated']);
        self::assertSame(1, $report['skipped']);
        self::assertEmpty($fakePages->savedContent);
    }

    public function testPassThroughRendererMatrixBlocks(): void
    {
        $content = json_encode([
            'blocks' => [
                [
                    'id' => 'proof-1',
                    'type' => 'proof',
                    'variant' => 'metric-callout',
                    'data' => ['items' => [['value' => '42%', 'label' => 'Win rate']]],
                ],
                [
                    'id' => 'stat-1',
                    'type' => 'stat_strip',
                    'variant' => 'three-up',
                    'data' => ['items' => [['value' => '3', 'label' => 'Metrics']]],
                ],
                [
                    'id' => 'faq-1',
                    'type' => 'faq',
                    'variant' => 'accordion',
                    'data' => ['items' => [['question' => 'Q', 'answer' => 'A']]],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(5, 'default', 'renderer', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(1, $report['migrated']);
        self::assertCount(1, $fakePages->savedContent);

        $saved = json_decode($fakePages->savedContent[0]['content'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('proof', $saved['blocks'][0]['type']);
        self::assertSame(['items' => [['value' => '42%', 'label' => 'Win rate']]], $saved['blocks'][0]['data']);
        self::assertSame('stat_strip', $saved['blocks'][1]['type']);
        self::assertSame(['items' => [['value' => '3', 'label' => 'Metrics']]], $saved['blocks'][1]['data']);
        self::assertSame('faq', $saved['blocks'][2]['type']);
        self::assertSame(['items' => [['question' => 'Q', 'answer' => 'A']]], $saved['blocks'][2]['data']);
    }

    public function testRejectsBlocksOutsideRendererMatrix(): void
    {
        $content = json_encode([
            'blocks' => [
                [
                    'id' => 'mystery',
                    'type' => 'not_a_block',
                    'variant' => 'unknown',
                    'data' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(6, 'default', 'unknown-renderer', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(0, $report['migrated']);
        self::assertSame(1, $report['errors']['total']);
        self::assertSame(1, $report['errors']['unknown_block_type']);
        self::assertEmpty($fakePages->savedContent);
    }

    public function testSplitsRichTextSectionsIntoSemanticBlocks(): void
    {
        $html = <<<HTML
<section>
  <h1>Headline</h1>
  <p>Subline</p>
  <a href="/go">Start</a>
</section>
<section class="uk-section">
  <h2>Highlights</h2>
  <ul>
    <li>First</li>
    <li>Second</li>
    <li>Third</li>
  </ul>
</section>
<section>
  <h2>Steps</h2>
  <ol>
    <li>One</li>
    <li>Two</li>
  </ol>
</section>
<section>
  <a href="/cta">Jetzt starten</a>
</section>
HTML;

        $content = json_encode([
            'blocks' => [
                [
                    'id' => 'legacy',
                    'type' => 'rich_text',
                    'variant' => 'prose',
                    'data' => ['body' => $html, 'alignment' => 'start'],
                    'tokens' => ['spacing' => 'normal', 'width' => 'normal'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $page = $this->createPage(4, 'default', 'split', $content);
        $fakePages = new FakePageService([$page]);

        $schema = dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $migrator = new PageBlockContractMigrator($fakePages, $schema);

        $report = $migrator->migrateAll();

        self::assertSame(1, $report['migrated']);
        self::assertSame(1, $report['semantic']['split']);
        self::assertCount(1, $fakePages->savedContent);

        $saved = json_decode($fakePages->savedContent[0]['content'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($saved['meta']['annotations']['semanticSplit']);
        self::assertFalse($saved['meta']['annotations']['reviewed']);

        self::assertCount(4, $saved['blocks']);
        self::assertSame('hero', $saved['blocks'][0]['type']);
        self::assertSame('feature_list', $saved['blocks'][1]['type']);
        self::assertSame('process_steps', $saved['blocks'][2]['type']);
        self::assertSame('cta', $saved['blocks'][3]['type']);
    }

    private function createPage(int $id, string $namespace, string $slug, string $content): Page
    {
        return new Page(
            $id,
            $namespace,
            $slug,
            'Title',
            $content,
            null,
            null,
            0,
            null,
            null,
            null,
            null,
            false
        );
    }
}
