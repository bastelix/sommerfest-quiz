<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Domain\CmsFooterBlock;
use App\Repository\ProjectSettingsRepository;
use App\Service\CmsFooterBlockService;
use PDO;

final class FooterTools
{
    private CmsFooterBlockService $footerBlocks;
    private ProjectSettingsRepository $settingsRepo;

    private const NS_PROP = ['type' => 'string', 'description' => 'Optional namespace (defaults to the token namespace)'];

    private const ALLOWED_TYPES = ['menu', 'text', 'social', 'contact', 'newsletter', 'html'];
    private const ALLOWED_SLOTS = ['footer_1', 'footer_2', 'footer_3'];
    private const ALLOWED_LAYOUTS = ['equal', 'brand-left', 'cta-right', 'centered'];

    public function __construct(private readonly PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->footerBlocks = new CmsFooterBlockService($pdo);
        $this->settingsRepo = new ProjectSettingsRepository($pdo);
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
                'name' => 'list_footer_blocks',
                'method' => 'listFooterBlocks',
                'description' => 'List footer blocks for a namespace and slot. Slots: footer_1, footer_2, footer_3. Block types: menu, text, social, contact, newsletter, html.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slot' => ['type' => 'string', 'enum' => self::ALLOWED_SLOTS, 'description' => 'Footer slot (footer_1, footer_2, footer_3). If omitted, returns blocks for all slots.'],
                        'locale' => ['type' => 'string', 'description' => 'Optional locale filter (e.g. de, en)'],
                        'includeInactive' => ['type' => 'boolean', 'description' => 'Include inactive blocks (default false)'],
                    ],
                ],
            ],
            [
                'name' => 'create_footer_block',
                'method' => 'createFooterBlock',
                'description' => 'Create a new footer block. Types: menu, text, social, contact, newsletter, html. Slots: footer_1, footer_2, footer_3.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slot' => ['type' => 'string', 'enum' => self::ALLOWED_SLOTS, 'description' => 'Footer slot (footer_1, footer_2, footer_3)'],
                        'type' => ['type' => 'string', 'enum' => self::ALLOWED_TYPES, 'description' => 'Block type (menu, text, social, contact, newsletter, html)'],
                        'content' => ['type' => 'object', 'description' => 'Block content as JSON object (structure depends on type)'],
                        'position' => ['type' => 'integer', 'description' => 'Sort position within the slot (default 0)'],
                        'locale' => ['type' => 'string', 'description' => 'Locale (default de)'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the block is active (default true)'],
                    ],
                    'required' => ['slot', 'type', 'content'],
                ],
            ],
            [
                'name' => 'update_footer_block',
                'method' => 'updateFooterBlock',
                'description' => 'Update an existing footer block by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'blockId' => ['type' => 'integer', 'description' => 'ID of the footer block to update'],
                        'type' => ['type' => 'string', 'enum' => self::ALLOWED_TYPES, 'description' => 'Block type'],
                        'content' => ['type' => 'object', 'description' => 'Block content as JSON object'],
                        'position' => ['type' => 'integer', 'description' => 'Sort position within the slot'],
                        'slot' => ['type' => 'string', 'enum' => self::ALLOWED_SLOTS, 'description' => 'Move block to a different slot'],
                        'isActive' => ['type' => 'boolean', 'description' => 'Whether the block is active'],
                    ],
                    'required' => ['blockId', 'type', 'content'],
                ],
            ],
            [
                'name' => 'delete_footer_block',
                'method' => 'deleteFooterBlock',
                'description' => 'Delete a footer block by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'blockId' => ['type' => 'integer', 'description' => 'ID of the footer block to delete'],
                    ],
                    'required' => ['blockId'],
                ],
            ],
            [
                'name' => 'reorder_footer_blocks',
                'method' => 'reorderFooterBlocks',
                'description' => 'Reorder footer blocks within a slot by providing an ordered list of block IDs.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'slot' => ['type' => 'string', 'enum' => self::ALLOWED_SLOTS, 'description' => 'Footer slot to reorder'],
                        'locale' => ['type' => 'string', 'description' => 'Locale (default de)'],
                        'orderedIds' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ordered list of block IDs'],
                    ],
                    'required' => ['slot', 'orderedIds'],
                ],
            ],
            [
                'name' => 'get_footer_layout',
                'method' => 'getFooterLayout',
                'description' => 'Get the footer layout preference for a namespace. Possible layouts: equal, brand-left, cta-right, centered.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                    ],
                ],
            ],
            [
                'name' => 'update_footer_layout',
                'method' => 'updateFooterLayout',
                'description' => 'Update the footer layout preference for a namespace. Layouts: equal, brand-left, cta-right, centered.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'layout' => ['type' => 'string', 'enum' => self::ALLOWED_LAYOUTS, 'description' => 'Footer layout (equal, brand-left, cta-right, centered)'],
                    ],
                    'required' => ['layout'],
                ],
            ],
        ];
    }

    public function listFooterBlocks(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : null;
        $onlyActive = !(isset($args['includeInactive']) && $args['includeInactive'] === true);

        $slot = isset($args['slot']) && is_string($args['slot']) ? trim($args['slot']) : null;
        $slots = $slot !== null ? [$slot] : self::ALLOWED_SLOTS;

        $result = [];
        foreach ($slots as $s) {
            $blocks = $this->footerBlocks->getBlocksForSlot($ns, $s, $locale, $onlyActive);
            $result[$s] = array_map(fn(CmsFooterBlock $b) => $this->serializeBlock($b), $blocks);
        }

        return ['namespace' => $ns, 'slots' => $result];
    }

    public function createFooterBlock(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $slot = isset($args['slot']) && is_string($args['slot']) ? trim($args['slot']) : '';
        if ($slot === '') {
            throw new \InvalidArgumentException('slot is required');
        }

        $type = isset($args['type']) && is_string($args['type']) ? trim($args['type']) : '';
        if ($type === '') {
            throw new \InvalidArgumentException('type is required');
        }

        $content = isset($args['content']) && is_array($args['content']) ? $args['content'] : null;
        if ($content === null) {
            throw new \InvalidArgumentException('content must be an object');
        }

        $position = isset($args['position']) && is_int($args['position']) ? $args['position'] : 0;
        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : 'de';
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : true;

        $block = $this->footerBlocks->createBlock($ns, $slot, $type, $content, $position, $locale, $isActive);

        return ['status' => 'created', 'block' => $this->serializeBlock($block)];
    }

    public function updateFooterBlock(array $args): array
    {
        $blockId = isset($args['blockId']) ? (int) $args['blockId'] : 0;
        if ($blockId <= 0) {
            throw new \InvalidArgumentException('blockId is required');
        }

        $existing = $this->footerBlocks->getBlockById($blockId);
        if ($existing === null) {
            throw new \InvalidArgumentException('Footer block not found');
        }

        $type = isset($args['type']) && is_string($args['type']) ? trim($args['type']) : '';
        if ($type === '') {
            throw new \InvalidArgumentException('type is required');
        }

        $content = isset($args['content']) && is_array($args['content']) ? $args['content'] : null;
        if ($content === null) {
            throw new \InvalidArgumentException('content must be an object');
        }

        $position = isset($args['position']) && is_int($args['position']) ? $args['position'] : $existing->getPosition();
        $isActive = isset($args['isActive']) ? (bool) $args['isActive'] : $existing->isActive();
        $slot = isset($args['slot']) && is_string($args['slot']) ? trim($args['slot']) : null;

        $block = $this->footerBlocks->updateBlock($blockId, $type, $content, $position, $isActive, $slot);

        return ['status' => 'updated', 'block' => $this->serializeBlock($block)];
    }

    public function deleteFooterBlock(array $args): array
    {
        $blockId = isset($args['blockId']) ? (int) $args['blockId'] : 0;
        if ($blockId <= 0) {
            throw new \InvalidArgumentException('blockId is required');
        }

        $this->footerBlocks->deleteBlock($blockId);

        return ['status' => 'deleted'];
    }

    public function reorderFooterBlocks(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $slot = isset($args['slot']) && is_string($args['slot']) ? trim($args['slot']) : '';
        if ($slot === '') {
            throw new \InvalidArgumentException('slot is required');
        }

        $orderedIds = $args['orderedIds'] ?? null;
        if (!is_array($orderedIds)) {
            throw new \InvalidArgumentException('orderedIds must be an array of block IDs');
        }

        $locale = isset($args['locale']) && is_string($args['locale']) ? trim($args['locale']) : 'de';

        $this->footerBlocks->reorderBlocks($ns, $slot, $locale, $orderedIds);

        return ['status' => 'reordered', 'namespace' => $ns, 'slot' => $slot];
    }

    public function getFooterLayout(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $row = $this->settingsRepo->fetch($ns);
        $layout = isset($row['footer_layout']) && is_string($row['footer_layout']) && $row['footer_layout'] !== ''
            ? $row['footer_layout']
            : 'equal';

        return ['namespace' => $ns, 'layout' => $layout];
    }

    public function updateFooterLayout(array $args): array
    {
        $ns = $this->resolveNamespace($args);

        $layout = isset($args['layout']) && is_string($args['layout']) ? trim($args['layout']) : '';
        if (!in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            throw new \InvalidArgumentException('layout must be one of: ' . implode(', ', self::ALLOWED_LAYOUTS));
        }

        $this->settingsRepo->updateFooterLayout($ns, $layout);

        return ['status' => 'updated', 'namespace' => $ns, 'layout' => $layout];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBlock(CmsFooterBlock $block): array
    {
        return [
            'id' => $block->getId(),
            'namespace' => $block->getNamespace(),
            'slot' => $block->getSlot(),
            'type' => $block->getType(),
            'content' => $block->getContent(),
            'position' => $block->getPosition(),
            'locale' => $block->getLocale(),
            'isActive' => $block->isActive(),
            'updatedAt' => $block->getUpdatedAt()?->format(\DATE_ATOM),
        ];
    }
}
