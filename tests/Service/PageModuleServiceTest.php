<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\PageModule;
use App\Service\PageModuleService;
use App\Service\PageService;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class PageModuleServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE page_modules('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'page_id INTEGER NOT NULL, '
            . 'type TEXT NOT NULL, '
            . 'config TEXT NOT NULL DEFAULT \'{}\', '
            . 'position TEXT NOT NULL'
            . ')'
        );
        $pdo->exec(
            'CREATE TABLE pages('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'namespace TEXT NOT NULL DEFAULT \'default\', '
            . 'slug TEXT NOT NULL, '
            . 'content TEXT'
            . ')'
        );

        return $pdo;
    }

    private function createService(?PDO $pdo = null): PageModuleService
    {
        $pdo = $pdo ?? $this->createPdo();
        $pages = $this->createStub(PageService::class);
        $pages->method('findById')->willReturn(null);

        return new PageModuleService($pdo, $pages);
    }

    public function testAllowedTypesIncludesTimeline(): void
    {
        $this->assertContains('timeline', PageModuleService::ALLOWED_TYPES);
    }

    public function testCreateTimelineModule(): void
    {
        $pdo = $this->createPdo();
        $service = $this->createService($pdo);

        $config = [
            'heading' => 'Meine Stationen',
            'items' => [
                ['title' => '1992-1996: Elektriker-Ausbildung', 'description' => 'HWK Rathenow.'],
                ['title' => '2001-2002: Bundeswehr Luftwaffe', 'description' => 'Kalibriertechnik-Meister.'],
            ],
        ];

        $module = $service->create(1, 'timeline', $config, 'after-content');

        $this->assertInstanceOf(PageModule::class, $module);
        $this->assertSame('timeline', $module->getType());
        $this->assertSame('after-content', $module->getPosition());
        $this->assertSame('Meine Stationen', $module->getConfig()['heading']);
        $this->assertCount(2, $module->getConfig()['items']);
    }

    public function testUpdateTimelineModule(): void
    {
        $pdo = $this->createPdo();
        $service = $this->createService($pdo);

        $module = $service->create(1, 'timeline', ['heading' => 'Old'], 'after-content');

        $updated = $service->update($module->getId(), 'timeline', ['heading' => 'New'], 'before-content');

        $this->assertSame('New', $updated->getConfig()['heading']);
        $this->assertSame('before-content', $updated->getPosition());
    }

    public function testDeleteTimelineModule(): void
    {
        $pdo = $this->createPdo();
        $service = $this->createService($pdo);

        $module = $service->create(1, 'timeline', ['heading' => 'Test'], 'after-content');
        $id = $module->getId();

        $this->assertNotNull($service->findById($id));

        $service->delete($id);

        $this->assertNull($service->findById($id));
    }

    public function testGetModulesByPositionGroupsCorrectly(): void
    {
        $pdo = $this->createPdo();
        $service = $this->createService($pdo);

        $service->create(1, 'timeline', ['heading' => 'Before'], 'before-content');
        $service->create(1, 'timeline', ['heading' => 'After'], 'after-content');

        $grouped = $service->getModulesByPosition(1);

        $this->assertArrayHasKey('before-content', $grouped);
        $this->assertArrayHasKey('after-content', $grouped);
        $this->assertCount(1, $grouped['before-content']);
        $this->assertCount(1, $grouped['after-content']);
        $this->assertSame('Before', $grouped['before-content'][0]->getConfig()['heading']);
        $this->assertSame('After', $grouped['after-content'][0]->getConfig()['heading']);
    }

    public function testTimelineWithClosingSection(): void
    {
        $pdo = $this->createPdo();
        $service = $this->createService($pdo);

        $config = [
            'heading' => 'Timeline',
            'items' => [
                ['title' => 'Step 1', 'description' => 'First step'],
            ],
            'closing' => [
                'title' => 'Closing Title',
                'body' => 'Closing body text',
            ],
        ];

        $module = $service->create(1, 'timeline', $config, 'after-content');

        $this->assertSame('Closing Title', $module->getConfig()['closing']['title']);
        $this->assertSame('Closing body text', $module->getConfig()['closing']['body']);
    }
}
