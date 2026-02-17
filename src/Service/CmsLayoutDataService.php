<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use PDO;

use function is_string;
use function ltrim;
use function rtrim;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Loads shared layout chrome (header config, logo, navigation, footer)
 * for marketing pages so that multiple controllers can render full-page
 * layouts without duplicating the data-gathering logic.
 */
final class CmsLayoutDataService
{
    private ProjectSettingsService $projectSettings;
    private CmsFooterBlockService $footerBlockService;
    private CmsMenuDefinitionService $menuDefinitionService;
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        ?ProjectSettingsService $projectSettings = null,
        ?CmsFooterBlockService $footerBlockService = null,
        ?CmsMenuDefinitionService $menuDefinitionService = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($this->pdo);
        $this->footerBlockService = $footerBlockService ?? new CmsFooterBlockService($this->pdo);
        $this->menuDefinitionService = $menuDefinitionService ?? new CmsMenuDefinitionService($this->pdo);
    }

    /**
     * Load all layout-chrome data needed to render a complete marketing page.
     *
     * @return array{
     *     cmsMainNavigation: array,
     *     cmsFooterNavigation: array,
     *     cmsLegalNavigation: array,
     *     cmsFooterColumns: array,
     *     cmsFooterBlocks: array,
     *     cmsFooterLayout: string,
     *     headerConfig: array,
     *     headerLogo: array
     * }
     */
    public function loadLayoutData(
        string $namespace,
        ?int $pageId,
        ?string $locale,
        string $basePath
    ): array {
        $menuResolver = new CmsMenuResolverService($this->pdo);

        $headerNavigation = $menuResolver->resolveMenu($namespace, 'header', $pageId, $locale);
        $cmsMainNavigation = $headerNavigation['items'];

        $footerNavigation = $menuResolver->resolveMenu($namespace, 'footer', $pageId, $locale);
        $cmsFooterNavigation = $footerNavigation['items'];

        $legalNavigation = $menuResolver->resolveMenu($namespace, 'legal', $pageId, $locale);
        $cmsLegalNavigation = $legalNavigation['items'];

        $cmsFooterColumns = $this->resolveFooterColumns($menuResolver, $namespace, $pageId, $locale);
        $cmsFooterBlocks = $this->resolveFooterBlocks($namespace, $locale ?? 'de');
        $cmsFooterLayout = $this->projectSettings->getFooterLayout($namespace);

        $cookieSettings = $this->projectSettings->getCookieConsentSettings($namespace);
        $headerConfig = $this->buildHeaderConfig($cookieSettings);
        $headerLogo = $this->buildHeaderLogoSettings($cookieSettings, $basePath);

        return [
            'cmsMainNavigation' => $cmsMainNavigation,
            'cmsFooterNavigation' => $cmsFooterNavigation,
            'cmsLegalNavigation' => $cmsLegalNavigation,
            'cmsFooterColumns' => $cmsFooterColumns,
            'cmsFooterBlocks' => $cmsFooterBlocks,
            'cmsFooterLayout' => $cmsFooterLayout,
            'headerConfig' => $headerConfig,
            'headerLogo' => $headerLogo,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function buildHeaderConfig(array $settings): array
    {
        return [
            'show_language' => (bool) ($settings['show_language_toggle'] ?? true),
            'show_theme_toggle' => (bool) ($settings['show_theme_toggle'] ?? true),
            'show_contrast_toggle' => (bool) ($settings['show_contrast_toggle'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{mode:string,src:?string,alt:string,label:string,path:string}
     */
    public function buildHeaderLogoSettings(array $settings, string $basePath): array
    {
        $mode = is_string($settings['header_logo_mode'] ?? null)
            ? strtolower(trim((string) $settings['header_logo_mode']))
            : 'text';
        $path = is_string($settings['header_logo_path'] ?? null)
            ? trim((string) $settings['header_logo_path'])
            : '';
        $alt = is_string($settings['header_logo_alt'] ?? null)
            ? trim((string) $settings['header_logo_alt'])
            : '';
        $label = is_string($settings['header_logo_label'] ?? null)
            ? trim((string) $settings['header_logo_label'])
            : '';
        if ($label === '') {
            $label = $alt !== '' ? $alt : 'QuizRace';
        }
        if ($alt === '') {
            $alt = $label;
        }
        $src = $this->resolveHeaderLogoPath($path, $basePath);

        if ($mode !== 'image' || $src === null) {
            $mode = 'text';
        }

        return [
            'mode' => $mode,
            'src' => $src,
            'alt' => $alt,
            'label' => $label,
            'path' => $path,
        ];
    }

    private function resolveHeaderLogoPath(?string $path, string $basePath): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $normalized = trim($path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        $normalizedBase = rtrim($basePath, '/');

        return ($normalizedBase !== '' ? $normalizedBase : '') . '/' . ltrim($normalized, '/');
    }

    private function resolveFooterColumns(
        CmsMenuResolverService $menuResolver,
        string $namespace,
        ?int $pageId,
        ?string $locale
    ): array {
        $columns = [];

        foreach (CmsMenuResolverService::FOOTER_SLOTS as $slot) {
            $resolved = $menuResolver->resolveMenu($namespace, $slot, $pageId, $locale);
            if ($resolved['items'] === []) {
                continue;
            }

            $columns[] = [
                'slot' => $slot,
                'items' => $resolved['items'],
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function resolveFooterBlocks(string $namespace, string $locale): array
    {
        $result = [];

        foreach (['footer_1', 'footer_2', 'footer_3'] as $slot) {
            $blocks = $this->footerBlockService->getBlocksForSlot($namespace, $slot, $locale, true);
            $serialized = [];

            foreach ($blocks as $block) {
                $content = $block->getContent();

                if ($block->getType() === 'menu' && isset($content['menuId'])) {
                    $menuId = (int) $content['menuId'];
                    $menuItems = $this->menuDefinitionService->getMenuItemsForMenu($namespace, $menuId, $locale, true);
                    $content['menuItems'] = array_map(
                        static fn ($item): array => [
                            'label' => $item->getLabel(),
                            'href' => $item->getHref(),
                            'icon' => $item->getIcon(),
                            'isExternal' => $item->isExternal(),
                        ],
                        $menuItems
                    );
                }

                $serialized[] = [
                    'id' => $block->getId(),
                    'type' => $block->getType(),
                    'content' => $content,
                    'isActive' => $block->isActive(),
                ];
            }

            if ($serialized !== []) {
                $result[$slot] = $serialized;
            }
        }

        return $result;
    }
}
