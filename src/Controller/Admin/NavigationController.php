<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\CmsMenu;
use App\Domain\CmsMenuAssignment;
use App\Domain\Page;
use App\Infrastructure\Database;
use App\Repository\NamespaceRepository;
use App\Service\CmsMenuDefinitionService;
use App\Service\NamespaceAccessService;
use App\Service\NamespaceResolver;
use App\Service\NamespaceService;
use App\Service\PageService;
use App\Service\ProjectSettingsService;
use App\Support\FeatureFlags;
use App\Support\PageAnchorExtractor;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class NavigationController
{
    private PageService $pageService;
    private CmsMenuDefinitionService $menuDefinitions;
    private NamespaceResolver $namespaceResolver;
    private NamespaceRepository $namespaceRepository;
    private NamespaceService $namespaceService;
    private ProjectSettingsService $projectSettings;

    public function __construct(
        ?PDO $pdo = null,
        ?PageService $pageService = null,
        ?CmsMenuDefinitionService $menuDefinitions = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?NamespaceRepository $namespaceRepository = null,
        ?NamespaceService $namespaceService = null,
        ?ProjectSettingsService $projectSettings = null
    ) {
        $pdo = $pdo ?? Database::connectFromEnv();
        $this->pageService = $pageService ?? new PageService($pdo);
        $this->menuDefinitions = $menuDefinitions ?? new CmsMenuDefinitionService($pdo);
        $this->namespaceResolver = $namespaceResolver ?? new NamespaceResolver();
        $this->namespaceRepository = $namespaceRepository ?? new NamespaceRepository($pdo);
        $this->namespaceService = $namespaceService ?? new NamespaceService($this->namespaceRepository);
        $this->projectSettings = $projectSettings ?? new ProjectSettingsService($pdo);
    }

    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $namespaceList = array_values(array_unique(array_filter(array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            $availableNamespaces
        ), static fn (string $entryNamespace): bool => $entryNamespace !== '')));
        if ($namespaceList === [] && $namespace !== '') {
            $namespaceList = [$namespace];
        }

        $pages = $this->pageService->getAllForNamespaces($namespaceList);
        $anchorPage = $this->resolveAnchorPage($pages, $namespace, $request->getQueryParams());
        $internalLinks = $this->buildInternalLinks($pages);

        $menuDefinitions = $this->menuDefinitions->listMenus($namespace);
        $assignmentCounts = $this->countMenuAssignments($namespace);
        $menuDefinitionList = array_map(
            static fn (CmsMenu $menu): array => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
                'assignmentCount' => $assignmentCounts[$menu->getId()] ?? 0,
            ],
            $menuDefinitions
        );

        $selectedMenuId = (int) ($request->getQueryParams()['menuId'] ?? 0);
        if ($selectedMenuId <= 0 && $menuDefinitionList !== []) {
            $selectedMenuId = (int) $menuDefinitionList[0]['id'];
        }

        $footerLayout = $this->projectSettings->getFooterLayout($namespace);
        $footerMenuDefinitions = array_map(
            static fn (CmsMenu $menu): array => [
                'id' => $menu->getId(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
            ],
            $menuDefinitions
        );

        return $view->render($response, 'admin/navigation/index.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'menu_definitions' => $menuDefinitionList,
            'selected_menu_id' => $selectedMenuId,
            'internal_links' => $internalLinks,
            'use_navigation_tree' => FeatureFlags::marketingNavigationTreeEnabled(),
            'anchor_page_id' => $anchorPage?->getId(),
            'locale_options' => $this->resolveLocaleOptions($menuDefinitions, $namespace),
            'navigation_settings' => $this->projectSettings->getCookieConsentSettings($namespace),
            'footer_namespaces' => $namespaceList,
            'footer_layout' => $footerLayout,
            'footer_menu_definitions' => $footerMenuDefinitions,
        ]);
    }

    public function menusIndex(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $namespaceList = array_values(array_unique(array_filter(array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            $availableNamespaces
        ), static fn (string $entryNamespace): bool => $entryNamespace !== '')));
        if ($namespaceList === [] && $namespace !== '') {
            $namespaceList = [$namespace];
        }

        $pages = $this->pageService->getAllForNamespaces($namespaceList);
        $anchorPage = $this->resolveAnchorPage($pages, $namespace, $request->getQueryParams());
        $internalLinks = $this->buildInternalLinks($pages);

        $menuDefinitions = $this->menuDefinitions->listMenus($namespace);
        $assignmentCounts = $this->countMenuAssignments($namespace);
        $menuDefinitionList = array_map(
            static fn (CmsMenu $menu): array => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
                'assignmentCount' => $assignmentCounts[$menu->getId()] ?? 0,
            ],
            $menuDefinitions
        );

        $selectedMenuId = (int) ($request->getQueryParams()['menuId'] ?? 0);
        if ($selectedMenuId <= 0 && $menuDefinitionList !== []) {
            $selectedMenuId = (int) $menuDefinitionList[0]['id'];
        }

        $footerLayout = $this->projectSettings->getFooterLayout($namespace);
        $footerMenuDefinitions = array_map(
            static fn (CmsMenu $menu): array => [
                'id' => $menu->getId(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
            ],
            $menuDefinitions
        );

        return $view->render($response, 'admin/navigation/menus_index.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'menu_definitions' => $menuDefinitionList,
            'selected_menu_id' => $selectedMenuId,
            'internal_links' => $internalLinks,
            'use_navigation_tree' => FeatureFlags::marketingNavigationTreeEnabled(),
            'anchor_page_id' => $anchorPage?->getId(),
            'locale_options' => $this->resolveLocaleOptions($menuDefinitions, $namespace),
            'navigation_settings' => $this->projectSettings->getCookieConsentSettings($namespace),
            'footer_namespaces' => $namespaceList,
            'footer_layout' => $footerLayout,
            'footer_menu_definitions' => $footerMenuDefinitions,
        ]);
    }

    public function footerIndex(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $namespaceList = array_values(array_unique(array_filter(array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            $availableNamespaces
        ), static fn (string $entryNamespace): bool => $entryNamespace !== '')));
        if ($namespaceList === [] && $namespace !== '') {
            $namespaceList = [$namespace];
        }

        $menuDefinitions = $this->menuDefinitions->listMenus($namespace);
        $localeOptions = $this->resolveLocaleOptions($menuDefinitions, $namespace);
        $footerLayout = $this->projectSettings->getFooterLayout($namespace);

        return $view->render($response, 'admin/navigation/footer_index.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'footer_namespaces' => $namespaceList,
            'locale_options' => $localeOptions,
            'footer_layout' => $footerLayout,
            'footer_menu_definitions' => array_map(
                static fn (CmsMenu $menu): array => [
                    'id' => $menu->getId(),
                    'label' => $menu->getLabel(),
                    'locale' => $menu->getLocale(),
                ],
                $menuDefinitions
            ),
        ]);
    }

    public function menus(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        $namespaceList = array_values(array_unique(array_filter(array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            $availableNamespaces
        ), static fn (string $entryNamespace): bool => $entryNamespace !== '')));
        if ($namespaceList === [] && $namespace !== '') {
            $namespaceList = [$namespace];
        }

        $pages = $this->pageService->getAllForNamespaces($namespaceList);
        $anchorPage = $this->resolveAnchorPage($pages, $namespace, $request->getQueryParams());
        $internalLinks = $this->buildInternalLinks($pages);

        $menuDefinitions = $this->menuDefinitions->listMenus($namespace);
        $assignmentCounts = $this->countMenuAssignments($namespace);
        $menuDefinitionList = array_map(
            static fn (CmsMenu $menu): array => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(DATE_ATOM),
                'assignmentCount' => $assignmentCounts[$menu->getId()] ?? 0,
            ],
            $menuDefinitions
        );

        $selectedMenuId = (int) ($request->getQueryParams()['menuId'] ?? 0);
        if ($selectedMenuId <= 0 && $menuDefinitionList !== []) {
            $selectedMenuId = (int) $menuDefinitionList[0]['id'];
        }

        return $view->render($response, 'admin/navigation/menus.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'menu_definitions' => $menuDefinitionList,
            'selected_menu_id' => $selectedMenuId,
            'internal_links' => $internalLinks,
            'use_navigation_tree' => FeatureFlags::marketingNavigationTreeEnabled(),
            'anchor_page_id' => $anchorPage?->getId(),
            'locale_options' => $this->resolveLocaleOptions($menuDefinitions, $namespace),
        ]);
    }

    public function footerBlocks(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);

        $menuDefinitions = $this->menuDefinitions->listMenus($namespace);
        $localeOptions = $this->resolveLocaleOptions($menuDefinitions, $namespace);

        $namespaces = array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            $availableNamespaces
        );
        $namespaces = array_values(array_unique(array_filter($namespaces)));

        $footerLayout = $this->projectSettings->getFooterLayout($namespace);

        return $view->render($response, 'admin/navigation/footer-blocks.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'namespaces' => $namespaces,
            'currentNamespace' => $namespace,
            'localeOptions' => $localeOptions,
            'footerLayout' => $footerLayout,
            'menuDefinitions' => array_map(
                static fn (CmsMenu $menu): array => [
                    'id' => $menu->getId(),
                    'label' => $menu->getLabel(),
                    'locale' => $menu->getLocale(),
                ],
                $menuDefinitions
            ),
        ]);
    }

    public function headerSettings(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        [$availableNamespaces, $namespace] = $this->loadNamespaces($request);
        return $view->render($response, 'admin/settings/header.twig', [
            'role' => $_SESSION['user']['role'] ?? '',
            'currentPath' => $request->getUri()->getPath(),
            'domainType' => $request->getAttribute('domainType'),
            'available_namespaces' => $availableNamespaces,
            'pageNamespace' => $namespace,
            'csrf_token' => $this->ensureCsrfToken(),
            'navigation_settings' => $this->projectSettings->getCookieConsentSettings($namespace),
        ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function loadNamespaces(Request $request, ?string $preferredNamespace = null): array
    {
        $namespace = $preferredNamespace ?? $this->namespaceResolver->resolve($request)->getNamespace();
        $role = $_SESSION['user']['role'] ?? null;
        $accessService = new NamespaceAccessService();
        $allowedNamespaces = $accessService->resolveAllowedNamespaces(is_string($role) ? $role : null);

        try {
            $availableNamespaces = $this->namespaceService->allActive();
        } catch (\RuntimeException $exception) {
            $availableNamespaces = [];
        }

        if (
            $accessService->shouldExposeNamespace(PageService::DEFAULT_NAMESPACE, $allowedNamespaces, $role)
            && !array_filter(
                $availableNamespaces,
                static fn (array $entry): bool => $entry['namespace'] === PageService::DEFAULT_NAMESPACE
            )
        ) {
            $availableNamespaces[] = [
                'namespace' => PageService::DEFAULT_NAMESPACE,
                'label' => null,
                'is_active' => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $currentNamespaceExists = array_filter(
            $availableNamespaces,
            static fn (array $entry): bool => $entry['namespace'] === $namespace
        );
        if (
            !$currentNamespaceExists
            && $accessService->shouldExposeNamespace($namespace, $allowedNamespaces, $role)
        ) {
            $availableNamespaces[] = [
                'namespace' => $namespace,
                'label' => 'nicht gespeichert',
                'is_active' => false,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        if ($allowedNamespaces !== []) {
            foreach ($allowedNamespaces as $allowedNamespace) {
                if (
                    !array_filter(
                        $availableNamespaces,
                        static fn (array $entry): bool => $entry['namespace'] === $allowedNamespace
                    )
                ) {
                    $availableNamespaces[] = [
                        'namespace' => $allowedNamespace,
                        'label' => 'nicht gespeichert',
                        'is_active' => false,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        }

        $availableNamespaces = $accessService->filterNamespaceEntries($availableNamespaces, $allowedNamespaces, $role);

        return [$availableNamespaces, $namespace];
    }

    /**
     * @param array<int, Page> $pages
     * @return array<int, array{value:string,label:string,group:string}>
     */
    private function buildInternalLinks(array $pages): array
    {
        $extractor = new PageAnchorExtractor();
        $pagePathOptions = [];
        $pageAnchorOptions = [];

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if ($slug === '') {
                continue;
            }
            $namespace = $page->getNamespace();
            $path = '/' . ltrim($slug, '/');
            $pagePathOptions[$namespace . ':' . $path] = [
                'value' => $path,
                'label' => $namespace . ': ' . $path,
                'group' => 'Seitenpfade',
            ];

            $anchorIds = $extractor->extractAnchorIds($page->getContent());
            foreach ($anchorIds as $anchorId) {
                $pageAnchorOptions[$namespace . ':' . $path . '#' . $anchorId] = [
                    'value' => $path . '#' . $anchorId,
                    'label' => $namespace . ': ' . $path . '#' . $anchorId,
                    'group' => 'Seiten + Anker',
                ];
            }
        }

        uasort($pagePathOptions, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));
        uasort(
            $pageAnchorOptions,
            static fn (array $left, array $right): int => strcmp($left['label'], $right['label'])
        );

        return array_merge(
            array_values($pagePathOptions),
            array_values($pageAnchorOptions)
        );
    }

    /**
     * @param array<int, Page> $pages
     */
    private function resolveAnchorPage(array $pages, string $namespace, array $params): ?Page
    {
        $requestedSlug = '';
        if (isset($params['pageSlug']) || isset($params['slug'])) {
            $requestedSlug = trim((string) ($params['pageSlug'] ?? $params['slug'] ?? ''));
        }

        if ($requestedSlug !== '') {
            foreach ($pages as $page) {
                if ($page->getNamespace() === $namespace && $page->getSlug() === $requestedSlug) {
                    return $page;
                }
            }
        }

        foreach ($pages as $page) {
            if ($page->getNamespace() === $namespace) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function countMenuAssignments(string $namespace): array
    {
        $assignmentCounts = [];
        $assignments = $this->menuDefinitions->listAssignments($namespace, null, null, null, null, false);
        foreach ($assignments as $assignment) {
            $menuId = $assignment->getMenuId();
            $assignmentCounts[$menuId] = ($assignmentCounts[$menuId] ?? 0) + 1;
        }

        return $assignmentCounts;
    }

    /**
     * @param CmsMenu[] $menus
     * @param CmsMenuAssignment[] $assignments
     * @return list<string>
     */
    private function resolveLocaleOptions(array $menus, string $namespace, array $assignments = []): array
    {
        $locales = [];
        foreach ($menus as $menu) {
            if ($menu->getLocale() !== '') {
                $locales[] = $menu->getLocale();
            }
        }
        foreach ($assignments as $assignment) {
            $locale = $assignment->getLocale();
            if ($locale !== '') {
                $locales[] = $locale;
            }
        }
        if ($locales === []) {
            $locales = ['de', 'en'];
        }

        $locales = array_values(array_unique(array_map('strtolower', $locales)));
        sort($locales, SORT_STRING);

        $projectSettings = $this->projectSettings->getCookieConsentSettings($namespace);
        $languageToggleActive = (bool) $projectSettings['show_language_toggle'];
        if (!$languageToggleActive) {
            return [$locales[0]];
        }

        return $locales;
    }

    private function ensureCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
