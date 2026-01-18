<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\CmsMenu;
use App\Domain\CmsMenuAssignment;
use App\Domain\CmsMenuItem;
use App\Service\CmsMenuDefinitionService;
use App\Service\CmsMenuResolverService;
use App\Service\CmsMenuService;
use PHPUnit\Framework\TestCase;

final class CmsMenuResolverServiceTest extends TestCase
{
    public function testPrefersPageSpecificLocaleAssignment(): void
    {
        $menuDefinitions = $this->createMock(CmsMenuDefinitionService::class);
        $legacyMenuService = $this->createMock(CmsMenuService::class);

        $assignment = new CmsMenuAssignment(1, 12, 44, 'tenant', 'header', 'en', true, null);
        $menuDefinitions
            ->expects($this->once())
            ->method('getAssignmentForSlot')
            ->with('tenant', 'header', 'en', 44, true)
            ->willReturn($assignment);

        $menu = new CmsMenu(12, 'tenant', 'Header', 'en', true, null);
        $menuDefinitions
            ->expects($this->once())
            ->method('getMenuById')
            ->with('tenant', 12)
            ->willReturn($menu);

        $items = [
            $this->createMenuItem(10, 12, null, 'Home', '/home', 0, 'en'),
        ];
        $menuDefinitions
            ->expects($this->once())
            ->method('getMenuItemsForMenu')
            ->with('tenant', 12, 'en', true)
            ->willReturn($items);

        $resolver = new CmsMenuResolverService(null, $menuDefinitions, $legacyMenuService, null, false);

        $result = $resolver->resolveMenu('tenant', 'header', 44, 'en');

        $this->assertSame('page_locale', $result['source']);
        $this->assertSame('Home', $result['items'][0]['label']);
    }

    public function testFallsBackToDefaultLocaleAssignment(): void
    {
        $menuDefinitions = $this->createMock(CmsMenuDefinitionService::class);
        $legacyMenuService = $this->createMock(CmsMenuService::class);

        $assignment = new CmsMenuAssignment(2, 22, 99, 'tenant', 'header', 'de', true, null);
        $menuDefinitions
            ->expects($this->exactly(2))
            ->method('getAssignmentForSlot')
            ->withConsecutive(
                ['tenant', 'header', 'fr', 99, true],
                ['tenant', 'header', 'de', 99, true]
            )
            ->willReturnOnConsecutiveCalls(null, $assignment);

        $menu = new CmsMenu(22, 'tenant', 'Header', 'de', true, null);
        $menuDefinitions
            ->expects($this->once())
            ->method('getMenuById')
            ->with('tenant', 22)
            ->willReturn($menu);

        $items = [
            $this->createMenuItem(20, 22, null, 'Start', '/start', 0, 'de'),
        ];
        $menuDefinitions
            ->expects($this->once())
            ->method('getMenuItemsForMenu')
            ->with('tenant', 22, 'de', true)
            ->willReturn($items);

        $resolver = new CmsMenuResolverService(null, $menuDefinitions, $legacyMenuService, null, false);

        $result = $resolver->resolveMenu('tenant', 'header', 99, 'fr');

        $this->assertSame('page_default_locale', $result['source']);
        $this->assertSame('Start', $result['items'][0]['label']);
    }

    private function createMenuItem(
        int $id,
        int $menuId,
        ?int $parentId,
        string $label,
        string $href,
        int $position,
        string $locale
    ): CmsMenuItem {
        return new CmsMenuItem(
            $id,
            $menuId,
            $parentId,
            'tenant',
            $label,
            $href,
            null,
            $position,
            false,
            $locale,
            true,
            'link',
            null,
            null,
            null,
            false,
            null
        );
    }
}
