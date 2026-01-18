<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CmsMenuItem;
use App\Infrastructure\Database;
use App\Support\FeatureFlags;
use PDO;

final class CmsMenuResolverService
{
    private const DEFAULT_LOCALE = 'de';
    public const SLOT_MAIN = 'main';
    public const FOOTER_SLOTS = ['footer_1', 'footer_2', 'footer_3'];

    private CmsMenuDefinitionService $menuDefinitions;

    private CmsMenuService $legacyMenuService;

    private ?int $defaultMenuId;
    private bool $allowLegacyFallback;

    public function __construct(
        ?PDO $pdo = null,
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?CmsMenuService $legacyMenuService = null,
        ?int $defaultMenuId = null,
        ?bool $allowLegacyFallback = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService($pdo);
        $this->legacyMenuService = $legacyMenuService ?? new CmsMenuService($pdo);
        $this->defaultMenuId = $defaultMenuId;
        $this->allowLegacyFallback = $allowLegacyFallback ?? FeatureFlags::marketingMenuLegacyFallbackEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveMenu(string $namespace, string $slot, ?int $pageId, ?string $locale): array
    {
        $normalizedNamespace = trim($namespace);
        $normalizedSlot = trim($slot);

        if ($normalizedNamespace === '' || $normalizedSlot === '') {
            return [
                'menuId' => null,
                'assignmentId' => null,
                'items' => [],
                'source' => 'invalid',
            ];
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        $normalizedPageId = $pageId !== null && $pageId > 0 ? $pageId : null;
        $defaultLocale = self::DEFAULT_LOCALE;

        foreach ($this->buildPriorityList($normalizedPageId, $normalizedLocale, $defaultLocale) as $candidate) {
            $assignment = $this->menuDefinitions->getAssignmentForSlot(
                $normalizedNamespace,
                $normalizedSlot,
                $candidate['locale'],
                $candidate['pageId'],
                true
            );

            if ($assignment === null) {
                continue;
            }

            $menu = $this->menuDefinitions->getMenuById($normalizedNamespace, $assignment->getMenuId());
            if ($menu === null || !$menu->isActive()) {
                continue;
            }

            $items = $this->menuDefinitions->getMenuItemsForMenu(
                $normalizedNamespace,
                $menu->getId(),
                $candidate['locale'],
                true
            );

            return [
                'menuId' => $menu->getId(),
                'assignmentId' => $assignment->getId(),
                'items' => $this->buildMenuTree($items),
                'source' => $candidate['source'],
            ];
        }

        if ($this->defaultMenuId !== null && $this->defaultMenuId > 0) {
            $defaultMenu = $this->menuDefinitions->getMenuById($normalizedNamespace, $this->defaultMenuId);
            if ($defaultMenu !== null && $defaultMenu->isActive()) {
                $items = $this->menuDefinitions->getMenuItemsForMenu(
                    $normalizedNamespace,
                    $defaultMenu->getId(),
                    $normalizedLocale ?? $defaultLocale,
                    true
                );

                if ($items === [] && $normalizedLocale !== null && $normalizedLocale !== $defaultLocale) {
                    $items = $this->menuDefinitions->getMenuItemsForMenu(
                        $normalizedNamespace,
                        $defaultMenu->getId(),
                        $defaultLocale,
                        true
                    );
                }

                return [
                    'menuId' => $defaultMenu->getId(),
                    'assignmentId' => null,
                    'items' => $this->buildMenuTree($items),
                    'source' => 'default_menu',
                ];
            }
        }

        if ($this->allowLegacyFallback && !$this->menuDefinitions->hasAssignmentsForSlot($normalizedNamespace, $normalizedSlot)) {
            $legacyItems = $this->legacyMenuService->getMenuForNamespace($normalizedNamespace, $normalizedLocale);
            if ($legacyItems !== []) {
                return [
                    'menuId' => null,
                    'assignmentId' => null,
                    'items' => $legacyItems,
                    'source' => 'legacy_fallback',
                ];
            }
        }

        return [
            'menuId' => null,
            'assignmentId' => null,
            'items' => [],
            'source' => 'none',
        ];
    }

    /**
     * @return array<int, array{pageId: ?int, locale: string, source: string}>
     */
    private function buildPriorityList(?int $pageId, ?string $locale, string $defaultLocale): array
    {
        $seen = [];
        $priority = [];

        $this->addPriorityCandidate($priority, $seen, $pageId, $locale, 'page_locale');
        $this->addPriorityCandidate($priority, $seen, $pageId, $defaultLocale, 'page_default_locale');
        $this->addPriorityCandidate($priority, $seen, null, $locale, 'global_locale');
        $this->addPriorityCandidate($priority, $seen, null, $defaultLocale, 'global_default_locale');

        return $priority;
    }

    /**
     * @param array<int, array{pageId: ?int, locale: string, source: string}> $priority
     * @param array<string, bool> $seen
     */
    private function addPriorityCandidate(
        array &$priority,
        array &$seen,
        ?int $pageId,
        ?string $locale,
        string $source
    ): void {
        if ($pageId === null && str_starts_with($source, 'page_')) {
            return;
        }

        if ($locale === null || $locale === '') {
            return;
        }

        $key = sprintf('%s:%s', $pageId === null ? 'global' : (string) $pageId, $locale);
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $priority[] = [
            'pageId' => $pageId,
            'locale' => $locale,
            'source' => $source,
        ];
    }

    /**
     * @param CmsMenuItem[] $items
     * @return array<int, array<string, mixed>>
     */
    private function buildMenuTree(array $items): array
    {
        $knownIds = [];
        foreach ($items as $item) {
            $knownIds[$item->getId()] = true;
        }

        $grouped = [];
        foreach ($items as $item) {
            $parentKey = $item->getParentId();
            if ($parentKey === null || !isset($knownIds[$parentKey])) {
                $parentKey = 0;
            }
            $grouped[$parentKey][] = $item;
        }

        foreach ($grouped as &$group) {
            usort($group, static function (CmsMenuItem $a, CmsMenuItem $b): int {
                if ($a->getPosition() === $b->getPosition()) {
                    return $a->getId() <=> $b->getId();
                }
                return $a->getPosition() <=> $b->getPosition();
            });
        }
        unset($group);

        $build = function (int $parentKey) use (&$build, $grouped): array {
            $nodes = [];
            foreach ($grouped[$parentKey] ?? [] as $item) {
                $nodes[] = [
                    'id' => $item->getId(),
                    'menuId' => $item->getMenuId(),
                    'namespace' => $item->getNamespace(),
                    'parentId' => $item->getParentId(),
                    'label' => $item->getLabel(),
                    'href' => $item->getHref(),
                    'icon' => $item->getIcon(),
                    'layout' => $item->getLayout(),
                    'detailTitle' => $item->getDetailTitle(),
                    'detailText' => $item->getDetailText(),
                    'detailSubline' => $item->getDetailSubline(),
                    'position' => $item->getPosition(),
                    'isExternal' => $item->isExternal(),
                    'locale' => $item->getLocale(),
                    'isActive' => $item->isActive(),
                    'isStartpage' => $item->isStartpage(),
                    'children' => $build($item->getId()),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = strtolower(trim($locale));
        return $normalized !== '' ? $normalized : null;
    }
}
