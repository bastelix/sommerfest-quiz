<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Service\CmsMenuDefinitionService;
use App\Service\CmsPageMenuService;
use PDO;

final class MenuTools
{
    private CmsMenuDefinitionService $menus;
    private CmsPageMenuService $menuItems;

    public function __construct(PDO $pdo, private readonly string $namespace)
    {
        $this->menus = new CmsMenuDefinitionService($pdo);
        $this->menuItems = new CmsPageMenuService($pdo);
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
                'description' => 'List all menus for the namespace.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name' => 'create_menu',
                'method' => 'createMenu',
                'description' => 'Create a new menu.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string', 'description' => 'Menu label/name'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale (e.g. de, en)'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the menu is active (default true)'],
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
        ];
    }

    public function listMenus(array $args): array
    {
        $out = [];
        foreach ($this->menus->listMenus($this->namespace, false) as $menu) {
            $out[] = [
                'id' => $menu->getId(),
                'namespace' => $menu->getNamespace(),
                'label' => $menu->getLabel(),
                'locale' => $menu->getLocale(),
                'isActive' => $menu->isActive(),
                'updatedAt' => $menu->getUpdatedAt()?->format(\DATE_ATOM),
            ];
        }
        return ['namespace' => $this->namespace, 'menus' => $out];
    }

    public function createMenu(array $args): array
    {
        $label = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
        if ($label === '') {
            throw new \InvalidArgumentException('label is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : null;
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $menu = $this->menus->createMenu($this->namespace, $label, $locale, $isActive);
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

        $menu = $this->menus->updateMenu($this->namespace, $menuId, $label, $locale, $isActive);
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
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $this->menus->deleteMenu($this->namespace, $menuId);
        return ['status' => 'deleted'];
    }

    public function listMenuItems(array $args): array
    {
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menuId is required');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null;
        $items = $this->menus->getMenuItemsForMenu($this->namespace, $menuId, $locale, false);

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

        return ['namespace' => $this->namespace, 'menuId' => $menuId, 'items' => array_values($tree)];
    }

    public function createMenuItem(array $args): array
    {
        $menuId = isset($args['menuId']) ? (int) $args['menuId'] : 0;
        $label = isset($args['label']) && is_string($args['label']) ? $args['label'] : '';
        $href = isset($args['href']) && is_string($args['href']) ? $args['href'] : '';

        if ($menuId <= 0 || trim($label) === '' || trim($href) === '') {
            throw new \InvalidArgumentException('menuId, label, and href are required');
        }

        $item = $this->menuItems->createMenuItemForMenu(
            $menuId,
            $this->namespace,
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
}
