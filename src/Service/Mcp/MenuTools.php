<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\CmsMenuDefinitionService;
use App\Service\CmsMenuResolverService;
use App\Service\CmsPageMenuService;
use PDO;

final class MenuTools
{
    private CmsMenuDefinitionService $menus;
    private CmsPageMenuService $menuItems;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->menus = new CmsMenuDefinitionService($pdo);
        $this->menuItems = new CmsPageMenuService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_menus',
                'method' => 'listMenus',
                'description' => 'List all menus for a namespace.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'create_menu',
                'method' => 'createMenu',
                'description' => 'Create a new menu.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'label' => ['type' => 'string', 'description' => 'Menu label/name'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale (e.g. de, en)'],
                        'isActive' => [
                            'type' => 'boolean',
                            'description' => 'Whether the menu is active (default true)',
                        ],
                    ],
                    'required' => ['label'],
                ],
            ],
            [
                'name' => 'update_menu',
                'method' => 'updateMenu',
                'description' => 'Update an existing menu.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'menuId' => ['type' => 'integer', 'description' => 'ID of the menu to update'],
                        'label' => ['type' => 'string', 'description' => 'New menu label'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the menu is active'],
                    ],
                    'required' => ['menuId', 'label'],
                ],
            ],
            [
                'name' => 'delete_menu',
                'method' => 'deleteMenu',
                'description' => 'Delete a menu by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'menuId' => ['type' => 'integer', 'description' => 'ID of the menu to delete'],
                    ],
                    'required' => ['menuId'],
                ],
            ],
            [
                'name' => 'list_menu_items',
                'method' => 'listMenuItems',
                'description' => 'List all items for a menu, returned as a tree structure.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'menuId' => ['type' => 'integer', 'description' => 'ID of the menu'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale filter'],
                    ],
                    'required' => ['menuId'],
                ],
            ],
            [
                'name' => 'create_menu_item',
                'method' => 'createMenuItem',
                'description' => 'Create a new menu item.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'menuId' => ['type' => 'integer', 'description' => 'ID of the parent menu'],
                        'label' => ['type' => 'string', 'description' => 'Display label'],
                        'href' => ['type' => 'string', 'description' => 'Link URL'],
                        'icon' => ['type' => 'string', 'description' => 'Optional icon name'],
                        'parentId' => ['type' => 'integer', 'description' => 'Optional parent item ID for nesting'],
                        'position' => ['type' => 'integer', 'description' => 'Optional sort position'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale'],
                        'isExternal' => ['type' => 'boolean', 'description' => 'External link (default false)'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Active state (default true)'],
                    ],
                    'required' => ['menuId', 'label', 'href'],
                ],
            ],
            [
                'name' => 'update_menu_item',
                'method' => 'updateMenuItem',
                'description' => 'Update an existing menu item.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'itemId' => ['type' => 'integer', 'description' => 'ID of the menu item to update'],
                        'label' => ['type' => 'string', 'description' => 'Display label'],
                        'href' => ['type' => 'string', 'description' => 'Link URL'],
                        'icon' => ['type' => 'string', 'description' => 'Optional icon name'],
                        'parentId' => ['type' => 'integer', 'description' => 'Optional parent item ID'],
                        'position' => ['type' => 'integer', 'description' => 'Optional sort position'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale'],
                        'isExternal' => ['type' => 'boolean', 'description' => 'External link'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Active state'],
                    ],
                    'required' => ['itemId', 'label', 'href'],
                ],
            ],
            [
                'name' => 'delete_menu_item',
                'method' => 'deleteMenuItem',
                'description' => 'Delete a menu item by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'itemId' => ['type' => 'integer', 'description' => 'ID of the menu item to delete'],
                    ],
                    'required' => ['itemId'],
                ],
            ],
            [
                'name' => 'list_menu_assignments',
                'method' => 'listMenuAssignments',
                'description' => 'List menu-to-slot assignments for a namespace. Slots control where a menu appears: "main" = header navigation, "footer_1"/"footer_2"/"footer_3" = footer columns.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slot' => ['type' => 'string', 'description' => 'Filter by slot (main, footer_1, footer_2, footer_3)'],
                        'locale' => ['type' => 'string', 'description' => 'Filter by locale (e.g. de, en)'],
                        'menuId' => ['type' => 'integer', 'description' => 'Filter by menu ID'],
                        'pageId' => ['type' => 'integer', 'description' => 'Filter by page ID (null = global assignment)'],
                    ],
                ],
            ],
            [
                'name' => 'create_menu_assignment',
                'method' => 'createMenuAssignment',
                'description' => 'Assign a menu to a slot. Use slot "main" to set the header/navigation menu. Use pageId to override the menu for a specific page, or omit it for a global (namespace-wide) assignment.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'menuId' => ['type' => 'integer', 'description' => 'ID of the menu to assign'],
                        'slot' => ['type' => 'string', 'description' => 'Target slot: main, footer_1, footer_2, or footer_3'],
                        'locale' => ['type' => 'string', 'description' => 'Locale for this assignment (default: de)'],
                        'pageId' => ['type' => 'integer', 'description' => 'Optional page ID for page-specific override (omit for global)'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the assignment is active (default true)'],
                    ],
                    'required' => ['menuId', 'slot'],
                ],
            ],
            [
                'name' => 'update_menu_assignment',
                'method' => 'updateMenuAssignment',
                'description' => 'Update an existing menu assignment.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'assignmentId' => ['type' => 'integer', 'description' => 'ID of the assignment to update'],
                        'menuId' => ['type' => 'integer', 'description' => 'New menu ID'],
                        'slot' => ['type' => 'string', 'description' => 'New slot: main, footer_1, footer_2, or footer_3'],
                        'locale' => ['type' => 'string', 'description' => 'New locale'],
                        'pageId' => ['type' => 'integer', 'description' => 'New page ID (null for global)'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the assignment is active'],
                    ],
                    'required' => ['assignmentId', 'menuId', 'slot'],
                ],
            ],
            [
                'name' => 'delete_menu_assignment',
                'method' => 'deleteMenuAssignment',
                'description' => 'Delete a menu assignment by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'assignmentId' => ['type' => 'integer', 'description' => 'ID of the assignment to delete'],
                    ],
                    'required' => ['assignmentId'],
                ],
            ],
        ];
    }

    public function listMenus(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $out = [];
        foreach ($this->menus->listMenus($ns, false) as $menu) {
            $out[] = [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(\DATE_ATOM),
            ];
        }
        return ['namespace' => $ns, 'menus' => $out];
    }

    public function createMenu(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $label = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
        if ($label === '') {
            throw new \InvalidArgumentException('label is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : null;
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $menu = $this->menus->createMenu($ns, $label, $locale, $isActive);
        return [
            'status' => 'created',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
            ],
        ];
    }

    public function updateMenu(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $label = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
        if ($label === '') {
            throw new \InvalidArgumentException('label is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : null;
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $menu = $this->menus->updateMenu($ns, $menuId, $label, $locale, $isActive);
        return [
            'status' => 'updated',
            'menu' => [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
            ],
        ];
    }

    public function deleteMenu(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $this->menus->deleteMenu($ns, $menuId);
        return ['status' => 'deleted'];
    }

    public function listMenuItems(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null;
        $items = $this->menus->getMenuItemsForMenu($ns, $menuId, $locale, false);

        $nodes = [];
        foreach ($items as $item) {
            $nodes[$item->getId()] = [
                'id' => $item->getId(),
                'menuId' => $item->getMenuId(),
                'parentId' => $item->getParentId(),
                'label' => $item->getLabel(),
                'href' => $item->getHref(),
                'icon' => $item->getIcon(),
                'position' => $item->getPosition(),
                'locale' => $item->getLocale(),
                'isExternal' => $item->isExternal(),
                'isActive' => $item->isActive(),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parentId'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return ['namespace' => $ns, 'menuId' => $menuId, 'items' => array_values($tree)];
    }

    public function createMenuItem(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        $label = isset($args['label']) && is_string($args['label']) ? $args['label'] : '';
        $href = isset($args['href']) && is_string($args['href']) ? $args['href'] : '';

        if ($menuId <= 0 || trim($label) === '' || trim($href) === '') {
            throw new \InvalidArgumentException('menuId, label, and href are required');
        }

        $item = $this->menuItems->createMenuItemForMenu(
            $menuId,
            $ns,
            $label,
            $href,
            isset($args['icon']) && is_string($args['icon']) ? $args['icon'] : null,
            isset($args['parentId']) && is_numeric($args['parentId']) ? (int) $args['parentId'] : null,
            'link',
            null,
            null,
            null,
            isset($args['position']) && is_numeric($args['position']) ? (int) $args['position'] : null,
            isset($args['isExternal']) ? (bool) $args['isExternal'] : false,
            isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null,
            isset($args['isActive']) ? (bool) $args['isActive'] : true,
            false,
        );

        return ['status' => 'created', 'id' => $item->getId()];
    }

    public function updateMenuItem(array $args): array
    {
        $itemId = isset($args['itemId']) ? (int) $args['itemId'] : 0;
        $label = isset($args['label']) && is_string($args['label']) ? $args['label'] : '';
        $href = isset($args['href']) && is_string($args['href']) ? $args['href'] : '';

        if ($itemId <= 0 || trim($label) === '' || trim($href) === '') {
            throw new \InvalidArgumentException('itemId, label, and href are required');
        }

        $item = $this->menuItems->updateMenuItem(
            $itemId,
            $label,
            $href,
            isset($args['icon']) && is_string($args['icon']) ? $args['icon'] : null,
            isset($args['parentId']) && is_numeric($args['parentId']) ? (int) $args['parentId'] : null,
            'link',
            null,
            null,
            null,
            isset($args['position']) && is_numeric($args['position']) ? (int) $args['position'] : null,
            isset($args['isExternal']) ? (bool) $args['isExternal'] : false,
            isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null,
            isset($args['isActive']) ? (bool) $args['isActive'] : true,
            false,
        );

        return ['status' => 'updated', 'id' => $item->getId()];
    }

    public function deleteMenuItem(array $args): array
    {
        $itemId = isset($args['itemId']) ? (int) $args['itemId'] : 0;
        if ($itemId <= 0) {
            throw new \InvalidArgumentException('itemId is required');
        }

        $this->menuItems->deleteMenuItem($itemId);
        return ['status' => 'deleted'];
    }

    private function allowedSlots(): array
    {
        return array_merge([CmsMenuResolverService::SLOT_MAIN], CmsMenuResolverService::FOOTER_SLOTS);
    }

    private function serializeAssignment(\App\Domain\CmsMenuAssignment $assignment): array
    {
        return [
            'id' => $assignment->getId(),
            'menuId' => $assignment->getMenuId(),
            'pageId' => $assignment->getPageId(),
            'namespace' => $assignment->getNamespace(),
            'slot' => $assignment->getSlot(),
            'locale' => $assignment->getLocale(),
            'isActive' => $assignment->isActive(),
            'updatedAt' => $assignment->getUpdatedAt()?->format(\DATE_ATOM),
        ];
    }

    public function listMenuAssignments(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : null;
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : null;
        $slot = isset($args['slot']) && is_string($args['slot']) ? strtolower(trim($args['slot'])) : null;
        $locale = isset($args['locale']) && is_string($args['locale']) ? strtolower(trim($args['locale'])) : null;

        if ($slot !== null && $slot !== '' && !in_array($slot, $this->allowedSlots(), true)) {
            throw new \InvalidArgumentException('Invalid slot. Allowed: ' . implode(', ', $this->allowedSlots()));
        }

        $assignments = $this->menus->listAssignments(
            $ns,
            $menuId > 0 ? $menuId : null,
            $pageId > 0 ? $pageId : null,
            $slot !== '' ? $slot : null,
            $locale !== '' ? $locale : null,
            false
        );

        $out = [];
        foreach ($assignments as $assignment) {
            $out[] = $this->serializeAssignment($assignment);
        }

        return ['namespace' => $ns, 'assignments' => $out];
    }

    public function createMenuAssignment(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $slot = isset($args['slot']) && is_string($args['slot']) ? strtolower(trim($args['slot'])) : '';
        if ($slot === '' || !in_array($slot, $this->allowedSlots(), true)) {
            throw new \InvalidArgumentException('slot is required. Allowed: ' . implode(', ', $this->allowedSlots()));
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? strtolower(trim($args['locale'])) : null;
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : null;
        if ($pageId !== null && $pageId <= 0) {
            $pageId = null;
        }
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $assignment = $this->menus->createAssignment($ns, $menuId, $pageId, $slot, $locale, $isActive);

        return ['status' => 'created', 'assignment' => $this->serializeAssignment($assignment)];
    }

    public function updateMenuAssignment(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $assignmentId = isset($args['assignmentId']) ? (int) $args['assignmentId'] : 0;
        if ($assignmentId <= 0) {
            throw new \InvalidArgumentException('assignmentId is required');
        }

        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $slot = isset($args['slot']) && is_string($args['slot']) ? strtolower(trim($args['slot'])) : '';
        if ($slot === '' || !in_array($slot, $this->allowedSlots(), true)) {
            throw new \InvalidArgumentException('slot is required. Allowed: ' . implode(', ', $this->allowedSlots()));
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? strtolower(trim($args['locale'])) : null;
        $pageId = isset($args['pageId']) ? (int) $args['pageId'] : null;
        if ($pageId !== null && $pageId <= 0) {
            $pageId = null;
        }
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $assignment = $this->menus->updateAssignment($ns, $assignmentId, $menuId, $pageId, $slot, $locale, $isActive);

        return ['status' => 'updated', 'assignment' => $this->serializeAssignment($assignment)];
    }

    public function deleteMenuAssignment(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $assignmentId = isset($args['assignmentId']) ? (int) $args['assignmentId'] : 0;
        if ($assignmentId <= 0) {
            throw new \InvalidArgumentException('assignmentId is required');
        }

        if (!$this->menus->deleteAssignment($ns, $assignmentId)) {
            throw new \InvalidArgumentException('Menu assignment not found');
        }

        return ['status' => 'deleted'];
    }
}
