<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Page;
use DateTimeImmutable;
use RuntimeException;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_values;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function trim;

final class PageBlockContractMigrator
{
    public const MIGRATION_VERSION = 'block-contract-v1';

    /** @var array<string, list<string>> */
    private array $blockVariants;

    /** @var array<string, list<string>> */
    private array $tokenEnums;

    private PageService $pages;

    /** @var callable():DateTimeImmutable */
    private $clock;

    /** @var array<string, string> */
    private array $legacyTypeMap = [
        'text' => 'rich_text',
    ];

    /** @var array<string, string> */
    private array $legacyLayoutMap = [
        'media-right' => 'media_right',
        'media_right' => 'media_right',
        'media-left' => 'media_left',
        'media_left' => 'media_left',
        'horizontal' => 'horizontal',
        'vertical' => 'vertical',
        'grid' => 'grid',
    ];

    /** @var array<string, string> */
    private array $defaultTokens = [
        'background' => 'default',
        'spacing' => 'normal',
        'width' => 'normal',
    ];

    public function __construct(?PageService $pages = null, ?string $schemaPath = null, ?callable $clock = null)
    {
        $this->pages = $pages ?? new PageService();
        $schemaFile = $schemaPath ?? dirname(__DIR__, 2) . '/public/js/components/block-contract.schema.json';
        $schema = $this->loadSchema($schemaFile);
        [$this->blockVariants, $this->tokenEnums] = $this->extractSchemaData($schema);
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * Execute the migration for all pages and persist valid results.
     *
     * @return array{total:int,migrated:int,skipped:int,errors:array<string,int>,details:list<array<string,mixed>>}
     */
    public function migrateAll(): array
    {
        $pages = $this->pages->getAll();
        $report = [
            'total' => count($pages),
            'migrated' => 0,
            'skipped' => 0,
            'errors' => [
                'total' => 0,
                'unknown_block_type' => 0,
                'missing_required_data' => 0,
                'invalid_variant' => 0,
                'schema_violation' => 0,
                'invalid_json' => 0,
            ],
            'details' => [],
        ];

        foreach ($pages as $page) {
            $result = $this->migratePage($page);

            if ($result['status'] === 'migrated') {
                $report['migrated']++;
            } elseif ($result['status'] === 'skipped') {
                $report['skipped']++;
            } else {
                $report['errors']['total']++;
                $reason = $result['reason'] ?? 'schema_violation';
                if (isset($report['errors'][$reason])) {
                    $report['errors'][$reason]++;
                }
                $report['details'][] = [
                    'pageId' => $page->getId(),
                    'namespace' => $page->getNamespace(),
                    'slug' => $page->getSlug(),
                    'reason' => $reason,
                    'message' => $result['message'] ?? 'Migration failed',
                ];
            }
        }

        return $report;
    }

    /**
     * Migrate a single page and persist the new content when valid.
     *
     * @return array{status:string,reason?:string,message?:string}
     */
    public function migratePage(Page $page): array
    {
        $rawContent = $page->getContent();
        try {
            $parsed = $this->parseContent($rawContent);
        } catch (PageBlockMigrationException $exception) {
            return [
                'status' => 'error',
                'reason' => $exception->getReason(),
                'message' => $exception->getMessage(),
            ];
        }

        $alreadyValid = $this->validatePageContent($parsed['content']);
        if ($alreadyValid && $this->hasMigrationMarker($parsed['content'])) {
            return ['status' => 'skipped'];
        }

        try {
            $normalized = $this->normalizeContent($parsed['content']);
            $normalized['content']['meta'] = $this->stampMigrationMeta($normalized['content']['meta'] ?? []);
        } catch (PageBlockMigrationException $exception) {
            return [
                'status' => 'error',
                'reason' => $exception->getReason(),
                'message' => $exception->getMessage(),
            ];
        }

        if (!$this->validatePageContent($normalized['content'])) {
            return [
                'status' => 'error',
                'reason' => 'schema_violation',
                'message' => 'Normalized content failed contract validation',
            ];
        }

        $json = json_encode($normalized['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'status' => 'error',
                'reason' => 'schema_violation',
                'message' => 'Normalized content could not be encoded as JSON',
            ];
        }

        $this->pages->save($page->getNamespace(), $page->getSlug(), $json);

        return ['status' => 'migrated'];
    }

    /**
     * @return array{content:array<string,mixed>,source:string}
     */
    private function parseContent(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [
                'content' => [
                    'blocks' => [],
                    'meta' => [],
                    'id' => null,
                ],
                'source' => 'empty',
            ];
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                return [
                    'content' => [
                        'id' => $decoded['id'] ?? null,
                        'blocks' => $decoded['blocks'],
                        'meta' => is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [],
                    ],
                    'source' => 'json',
                ];
            }

            if (array_is_list($decoded)) {
                return [
                    'content' => [
                        'id' => null,
                        'blocks' => $decoded,
                        'meta' => [],
                    ],
                    'source' => 'json_blocks',
                ];
            }
        }

        // Fallback: treat legacy HTML as a rich text block to preserve content.
        return [
            'content' => [
                'id' => null,
                'blocks' => [
                    $this->buildRichTextBlock($trimmed),
                ],
                'meta' => [],
            ],
            'source' => 'html',
        ];
    }

    /**
     * @param array<string,mixed> $content
     *
     * @return array{content:array<string,mixed>}
     */
    private function normalizeContent(array $content): array
    {
        $blocks = $content['blocks'] ?? [];
        if (!is_array($blocks)) {
            throw new PageBlockMigrationException('invalid_json', 'Blocks payload must be an array');
        }

        $normalizedBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new PageBlockMigrationException('invalid_json', 'Block entry must be an object');
            }
            $normalizedBlocks[] = $this->normalizeBlock($block);
        }

        return [
            'content' => [
                'id' => is_string($content['id'] ?? null) ? $content['id'] : null,
                'blocks' => $normalizedBlocks,
                'meta' => is_array($content['meta'] ?? null) ? $content['meta'] : [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $block
     *
     * @return array<string,mixed>
     */
    private function normalizeBlock(array $block): array
    {
        $type = isset($block['type']) && is_string($block['type']) ? trim($block['type']) : '';
        if ($type === '') {
            throw new PageBlockMigrationException('unknown_block_type', 'Block type is missing');
        }

        $normalizedType = $this->legacyTypeMap[$type] ?? $type;
        if (!isset($this->blockVariants[$normalizedType])) {
            throw new PageBlockMigrationException('unknown_block_type', sprintf('Unsupported block type: %s', $type));
        }

        $variant = isset($block['variant']) && is_string($block['variant']) ? trim($block['variant']) : null;
        $determinedVariant = $this->determineVariant($normalizedType, $variant, $block);
        if ($determinedVariant === null) {
            throw new PageBlockMigrationException('invalid_variant', sprintf('Variant missing for %s', $normalizedType));
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $normalizedData = $this->normalizeBlockData($normalizedType, $data);
        $normalizedTokens = $this->normalizeTokens($block['tokens'] ?? null);
        $id = $this->normalizeBlockId($block['id'] ?? null);

        $normalizedBlock = [
            'id' => $id,
            'type' => $normalizedType,
            'variant' => $determinedVariant,
            'data' => $normalizedData,
        ];

        if ($normalizedTokens !== []) {
            $normalizedBlock['tokens'] = $normalizedTokens;
        }

        return $normalizedBlock;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeBlockData(string $type, array $data): array
    {
        return match ($type) {
            'hero' => $this->normalizeHeroData($data),
            'feature_list' => $this->normalizeFeatureListData($data),
            'process_steps' => $this->normalizeProcessStepsData($data),
            'testimonial' => $this->normalizeTestimonialData($data),
            'rich_text' => $this->normalizeRichTextData($data),
            default => throw new PageBlockMigrationException('unknown_block_type', sprintf('Unsupported block type: %s', $type)),
        };
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeHeroData(array $data): array
    {
        $headline = $this->requireString($data['headline'] ?? null, 'headline');
        $ctaRaw = is_array($data['cta'] ?? null) ? $data['cta'] : [];
        $cta = [
            'label' => $this->requireString($ctaRaw['label'] ?? null, 'cta.label'),
            'href' => $this->requireString($ctaRaw['href'] ?? null, 'cta.href'),
        ];

        $ariaLabel = $this->normalizeString($ctaRaw['ariaLabel'] ?? null);
        if ($ariaLabel !== null) {
            $cta['ariaLabel'] = $ariaLabel;
        }

        $normalized = [
            'headline' => $headline,
            'cta' => $cta,
        ];

        $eyebrow = $this->normalizeString($data['eyebrow'] ?? null);
        if ($eyebrow !== null) {
            $normalized['eyebrow'] = $eyebrow;
        }

        $subheadline = $this->normalizeString($data['subheadline'] ?? null);
        if ($subheadline !== null) {
            $normalized['subheadline'] = $subheadline;
        }

        $media = $this->normalizeMedia($data['media'] ?? null);
        if ($media !== null) {
            $normalized['media'] = $media;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeFeatureListData(array $data): array
    {
        $title = $this->requireString($data['title'] ?? null, 'title');
        $intro = $this->normalizeString($data['intro'] ?? null);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = $this->requireString($item['description'] ?? null, 'items.description');
            $normalizedItem = [
                'id' => $this->normalizeBlockId($item['id'] ?? null),
                'title' => $this->requireString($item['title'] ?? null, 'items.title'),
                'description' => $description,
            ];
            $icon = $this->normalizeString($item['icon'] ?? null);
            if ($icon !== null) {
                $normalizedItem['icon'] = $icon;
            }

            $media = $this->normalizeMedia($item['media'] ?? null);
            if ($media !== null) {
                $normalizedItem['media'] = $media;
            }

            $normalizedItems[] = $normalizedItem;
        }

        if ($normalizedItems === []) {
            throw new PageBlockMigrationException('missing_required_data', 'Feature list requires at least one item');
        }

        $normalized = [
            'title' => $title,
            'items' => array_values($normalizedItems),
        ];

        if ($intro !== null) {
            $normalized['intro'] = $intro;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeProcessStepsData(array $data): array
    {
        $title = $this->requireString($data['title'] ?? null, 'title');
        $summary = $this->normalizeString($data['summary'] ?? null);
        $steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];

        $normalizedSteps = [];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $normalizedStep = [
                'id' => $this->normalizeBlockId($step['id'] ?? null),
                'title' => $this->requireString($step['title'] ?? null, 'steps.title'),
                'description' => $this->requireString($step['description'] ?? null, 'steps.description'),
            ];
            $duration = $this->normalizeString($step['duration'] ?? null);
            if ($duration !== null) {
                $normalizedStep['duration'] = $duration;
            }
            $media = $this->normalizeMedia($step['media'] ?? null);
            if ($media !== null) {
                $normalizedStep['media'] = $media;
            }
            $normalizedSteps[] = $normalizedStep;
        }

        if (count($normalizedSteps) < 2) {
            throw new PageBlockMigrationException('missing_required_data', 'Process steps require at least two entries');
        }

        $normalized = [
            'title' => $title,
            'steps' => array_values($normalizedSteps),
        ];

        if ($summary !== null) {
            $normalized['summary'] = $summary;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeTestimonialData(array $data): array
    {
        $quote = $this->requireString($data['quote'] ?? null, 'quote');
        $authorRaw = is_array($data['author'] ?? null) ? $data['author'] : [];
        $author = [
            'name' => $this->requireString($authorRaw['name'] ?? null, 'author.name'),
        ];
        $role = $this->normalizeString($authorRaw['role'] ?? null);
        if ($role !== null) {
            $author['role'] = $role;
        }
        $avatar = $this->normalizeString($authorRaw['avatarId'] ?? null);
        if ($avatar !== null) {
            $author['avatarId'] = $avatar;
        }

        $normalized = [
            'quote' => $quote,
            'author' => $author,
        ];

        $source = $this->normalizeString($data['source'] ?? null);
        if ($source !== null) {
            $normalized['source'] = $source;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function normalizeRichTextData(array $data): array
    {
        $body = $this->requireString($data['body'] ?? null, 'body');
        $alignment = $this->normalizeString($data['alignment'] ?? null);
        $allowedAlignment = $this->tokenEnums['alignment'] ?? ['start', 'center', 'end', 'justify'];
        $normalizedAlignment = in_array($alignment, $allowedAlignment, true) ? $alignment : 'start';

        return [
            'body' => $body,
            'alignment' => $normalizedAlignment,
        ];
    }

    /**
     * @param array<string,mixed>|null $media
     *
     * @return array<string,mixed>|null
     */
    private function normalizeMedia($media): ?array
    {
        if (!is_array($media)) {
            return null;
        }

        $normalized = [];
        $imageId = $this->normalizeString($media['imageId'] ?? null);
        $alt = $this->normalizeString($media['alt'] ?? null);
        if ($imageId !== null) {
            $normalized['imageId'] = $imageId;
        }
        if ($alt !== null) {
            $normalized['alt'] = $alt;
        }

        if (is_array($media['focalPoint'] ?? null)) {
            $fp = $media['focalPoint'];
            if (isset($fp['x'], $fp['y']) && is_numeric($fp['x']) && is_numeric($fp['y'])) {
                $x = (float) $fp['x'];
                $y = (float) $fp['y'];
                if ($x >= 0 && $x <= 1 && $y >= 0 && $y <= 1) {
                    $normalized['focalPoint'] = ['x' => $x, 'y' => $y];
                }
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param array<string,mixed>|null $tokens
     *
     * @return array<string,string>
     */
    private function normalizeTokens($tokens): array
    {
        $normalized = [];
        if (is_array($tokens)) {
            foreach ($tokens as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    continue;
                }
                if (isset($this->tokenEnums[$key]) && in_array($value, $this->tokenEnums[$key], true)) {
                    $normalized[$key] = $value;
                }
            }
        }

        foreach ($this->defaultTokens as $token => $default) {
            if (!isset($normalized[$token]) && isset($this->tokenEnums[$token])) {
                $normalized[$token] = $default;
            }
        }

        return $normalized;
    }

    private function normalizeBlockId($id): string
    {
        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return $this->generateId();
    }

    /**
     * @param array<string,mixed> $block
     */
    private function determineVariant(string $type, ?string $variant, array $block): ?string
    {
        $variants = $this->blockVariants[$type] ?? [];
        if ($variant !== null && in_array($variant, $variants, true)) {
            return $variant;
        }

        $layoutRaw = null;
        if (isset($block['data']) && is_array($block['data'])) {
            $layoutRaw = $block['data']['layout'] ?? null;
        }
        $layout = is_string($layoutRaw) ? ($this->legacyLayoutMap[trim($layoutRaw)] ?? trim($layoutRaw)) : null;

        if ($type === 'hero') {
            if ($layout === 'media_right' && in_array('media_right', $variants, true)) {
                return 'media_right';
            }
            if ($layout === 'media_left' && in_array('media_left', $variants, true)) {
                return 'media_left';
            }
            if (isset($block['data']['media']) && in_array('media_right', $variants, true)) {
                return 'media_right';
            }
        }

        if ($type === 'feature_list') {
            if ($layout === 'grid' && in_array('icon_grid', $variants, true)) {
                return 'icon_grid';
            }
        }

        if ($type === 'process_steps') {
            if ($layout === 'horizontal' && in_array('timeline_horizontal', $variants, true)) {
                return 'timeline_horizontal';
            }
            if ($layout === 'vertical' && in_array('timeline_vertical', $variants, true)) {
                return 'timeline_vertical';
            }
        }

        return $variants[0] ?? null;
    }

    /**
     * @param array<string,mixed> $content
     */
    private function validatePageContent(array $content): bool
    {
        if (!isset($content['blocks']) || !is_array($content['blocks'])) {
            return false;
        }

        foreach ($content['blocks'] as $block) {
            if (!is_array($block)) {
                return false;
            }
            if (!isset($block['id'], $block['type'], $block['variant'], $block['data'])) {
                return false;
            }
            if (!is_string($block['id']) || trim($block['id']) === '') {
                return false;
            }
            $type = (string) $block['type'];
            $variant = (string) $block['variant'];
            if (!isset($this->blockVariants[$type]) || !in_array($variant, $this->blockVariants[$type], true)) {
                return false;
            }
            if (!is_array($block['data'])) {
                return false;
            }

            if (!$this->validateBlockData($type, $block['data'])) {
                return false;
            }

            if (isset($block['tokens'])) {
                $tokens = $block['tokens'];
                if (!is_array($tokens)) {
                    return false;
                }
                foreach ($tokens as $tokenKey => $tokenValue) {
                    if (!isset($this->tokenEnums[$tokenKey]) || !in_array($tokenValue, $this->tokenEnums[$tokenKey], true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validateBlockData(string $type, array $data): bool
    {
        return match ($type) {
            'hero' => isset($data['headline'], $data['cta']['label'], $data['cta']['href'])
                && $this->hasContent($data['headline'])
                && $this->hasContent($data['cta']['label'])
                && $this->hasContent($data['cta']['href']),
            'feature_list' => isset($data['title'], $data['items'])
                && $this->hasContent($data['title'])
                && is_array($data['items'])
                && count($data['items']) >= 1
                && $this->validateFeatureItems($data['items']),
            'process_steps' => isset($data['title'], $data['steps'])
                && $this->hasContent($data['title'])
                && is_array($data['steps'])
                && count($data['steps']) >= 2
                && $this->validateProcessSteps($data['steps']),
            'testimonial' => isset($data['quote'], $data['author']['name'])
                && $this->hasContent($data['quote'])
                && $this->hasContent($data['author']['name']),
            'rich_text' => isset($data['body']) && $this->hasContent($data['body']),
            default => false,
        };
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    private function validateFeatureItems(array $items): bool
    {
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'], $item['title'], $item['description'])) {
                return false;
            }
            if (!$this->hasContent($item['title']) || !$this->hasContent($item['description'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $steps
     */
    private function validateProcessSteps(array $steps): bool
    {
        foreach ($steps as $step) {
            if (!is_array($step) || !isset($step['id'], $step['title'], $step['description'])) {
                return false;
            }
            if (!$this->hasContent($step['title']) || !$this->hasContent($step['description'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $schema
     *
     * @return array{0:array<string,list<string>>,1:array<string,list<string>>}
     */
    private function extractSchemaData(array $schema): array
    {
        $variants = [];
        $tokenEnums = [];

        $blocks = $schema['oneOf'] ?? [];
        foreach ($blocks as $definition) {
            $type = $definition['properties']['type']['const'] ?? null;
            $variantEnum = $definition['properties']['variant']['enum'] ?? null;
            if (is_string($type) && is_array($variantEnum)) {
                $variants[$type] = array_values(array_filter(array_map('strval', $variantEnum)));
            }
        }

        $tokenProperties = $schema['definitions']['Tokens']['properties'] ?? [];
        foreach ($tokenProperties as $name => $tokenSchema) {
            if (is_array($tokenSchema['enum'] ?? null)) {
                $tokenEnums[$name] = array_values(array_map('strval', $tokenSchema['enum']));
            }
        }

        $alignmentEnum = $schema['definitions']['RichTextData']['properties']['alignment']['enum'] ?? null;
        if (is_array($alignmentEnum)) {
            $tokenEnums['alignment'] = array_values(array_map('strval', $alignmentEnum));
        }

        return [$variants, $tokenEnums];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadSchema(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Block contract schema not readable at %s', $path));
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Block contract schema is invalid JSON');
        }

        return $decoded;
    }

    private function normalizeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function requireString($value, string $field): string
    {
        if (!is_string($value)) {
            throw new PageBlockMigrationException('missing_required_data', sprintf('Missing required field: %s', $field));
        }

        $normalized = trim($value);
        if ($normalized === '') {
            throw new PageBlockMigrationException('missing_required_data', sprintf('Missing required field: %s', $field));
        }

        return $normalized;
    }

    private function generateId(): string
    {
        return sprintf('block-%s', bin2hex(random_bytes(8)));
    }

    private function hasContent($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string,mixed>
     */
    private function stampMigrationMeta(array $meta): array
    {
        $meta['migrationVersion'] = self::MIGRATION_VERSION;
        $meta['migratedAt'] = ($this->clock)()->format(DateTimeImmutable::ATOM);

        return $meta;
    }

    /**
     * @param array<string,mixed> $content
     */
    private function hasMigrationMarker(array $content): bool
    {
        $meta = $content['meta'] ?? null;
        if (!is_array($meta)) {
            return false;
        }

        return ($meta['migrationVersion'] ?? null) === self::MIGRATION_VERSION;
    }

    private function buildRichTextBlock(string $html): array
    {
        $body = trim($html);
        if ($body === '') {
            $body = '<p></p>';
        }

        return [
            'id' => $this->generateId(),
            'type' => 'rich_text',
            'variant' => $this->blockVariants['rich_text'][0] ?? 'prose',
            'data' => [
                'body' => $body,
                'alignment' => 'start',
            ],
            'tokens' => $this->normalizeTokens(null),
        ];
    }
}
