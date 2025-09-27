<?php

declare(strict_types=1);

namespace App\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Controller\Admin\LandingpageController;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use InvalidArgumentException;
use function array_key_exists;
use function in_array;
use function is_string;

/**
 * Collects media file references used on marketing landing pages.
 */
class LandingMediaReferenceService
{
    private PageService $pages;

    private PageSeoConfigService $seo;

    /**
     * @var list<string>
     */
    private array $excludedSlugs;

    private string $uploadPrefix;

    private string $uploadRoot;

    /**
     * @param list<string>|null $excludedSlugs
     */
    public function __construct(
        PageService $pages,
        PageSeoConfigService $seo,
        ?string $uploadPrefix = null,
        ?string $uploadRoot = null,
        ?array $excludedSlugs = null
    ) {
        $this->pages = $pages;
        $this->seo = $seo;
        $this->uploadPrefix = $this->normalizePrefix($uploadPrefix ?? '/uploads');
        $this->uploadRoot = $uploadRoot ?? dirname(__DIR__, 2) . '/data' . $this->uploadPrefix;
        $this->excludedSlugs = $excludedSlugs !== null
            ? array_values(array_filter($excludedSlugs, static fn ($value): bool => is_string($value) && $value !== ''))
            : LandingpageController::EXCLUDED_SLUGS;
    }

    /**
     * Return all available landing page slugs.
     *
     * @return list<string>
     */
    public function getAvailableSlugs(): array
    {
        $pages = $this->pages->getAll();
        $slugs = [];
        foreach ($pages as $page) {
            $slug = $page->getSlug();
            if (in_array($slug, $this->excludedSlugs, true)) {
                continue;
            }
            $slugs[$slug] = $slug;
        }

        $values = array_values($slugs);
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * Collect referenced upload paths for the provided landing slug.
     *
     * @return list<array{path:string,relativePath:string,sources:list<string>,missing:bool}>
     */
    public function getReferences(string $slug): array
    {
        $normalized = trim($slug);
        if ($normalized === '') {
            throw new InvalidArgumentException('missing slug');
        }

        $page = $this->pages->findBySlug($normalized);
        if ($page === null || in_array($page->getSlug(), $this->excludedSlugs, true)) {
            throw new InvalidArgumentException('unknown landing slug');
        }

        $references = [];
        $references = $this->collectFromContent($page, $references);
        $references = $this->collectFromSeo($page, $references);

        $result = [];
        foreach ($references as $path => $info) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $relative = $this->relativePath($path);
            if ($relative === null) {
                continue;
            }
            $result[] = [
                'path' => $path,
                'relativePath' => $relative,
                'sources' => array_values(array_unique($info['sources'] ?? [])),
                'missing' => !$this->fileExists($path),
            ];
        }

        usort(
            $result,
            static fn (array $a, array $b): int => strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''))
        );

        return $result;
    }

    /**
     * @param array<string,array{sources:list<string>}> $references
     * @return array<string,array{sources:list<string>}> $references
     */
    private function collectFromContent(Page $page, array $references): array
    {
        $html = $page->getContent();
        if (trim($html) === '') {
            return $references;
        }

        $dom = new DOMDocument();
        $internal = libxml_use_internal_errors(true);
        $markup = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($markup);
        if ($internal === false) {
            libxml_use_internal_errors(false);
        }
        libxml_clear_errors();

        $tagAttributes = [
            'img' => ['src', 'data-src', 'srcset', 'data-srcset'],
            'source' => ['src', 'srcset'],
            'video' => ['poster'],
        ];

        foreach ($tagAttributes as $tag => $attributes) {
            /** @var DOMNodeList<DOMElement> $nodes */
            $nodes = $dom->getElementsByTagName($tag);
            foreach ($nodes as $node) {
                foreach ($attributes as $attribute) {
                    if (!$node->hasAttribute($attribute)) {
                        continue;
                    }
                    $values = $this->extractAttributeValues($node->getAttribute($attribute), $attribute);
                    foreach ($values as $value) {
                        $this->registerReference(
                            $references,
                            $value,
                            sprintf('content:%s[%s]', $tag, $attribute)
                        );
                    }
                }
            }
        }

        return $references;
    }

    /**
     * @param array<string,array{sources:list<string>}> $references
     * @return array<string,array{sources:list<string>}> $references
     */
    private function collectFromSeo(Page $page, array $references): array
    {
        $config = $this->seo->load($page->getId());
        if (!$config instanceof PageSeoConfig) {
            return $references;
        }

        $this->registerReference($references, $config->getOgImage(), 'seo:ogImage');
        $this->registerReference($references, $config->getFaviconPath(), 'seo:faviconPath');

        return $references;
    }

    /**
     * @param array<string,array{sources:list<string>}> $references
     */
    private function registerReference(array &$references, ?string $rawValue, string $source): void
    {
        if ($rawValue === null) {
            return;
        }
        $normalized = $this->normalizePath($rawValue);
        if ($normalized === null) {
            return;
        }

        if (!array_key_exists($normalized, $references)) {
            $references[$normalized] = ['sources' => []];
        }

        if (!in_array($source, $references[$normalized]['sources'], true)) {
            $references[$normalized]['sources'][] = $source;
        }
    }

    /**
     * @return list<string>
     */
    private function extractAttributeValues(string $value, string $attribute): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        if ($attribute === 'srcset' || $attribute === 'data-srcset') {
            $parts = preg_split('/\s*,\s*/', $trimmed) ?: [];
            $urls = [];
            foreach ($parts as $part) {
                $candidate = trim((string) $part);
                if ($candidate === '') {
                    continue;
                }
                $spacePos = strpos($candidate, ' ');
                if ($spacePos !== false) {
                    $candidate = substr($candidate, 0, $spacePos);
                }
                if ($candidate !== '') {
                    $urls[] = $candidate;
                }
            }
            return $urls;
        }

        return [$trimmed];
    }

    private function normalizePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || str_starts_with($trimmed, 'data:')) {
            return null;
        }

        $candidate = $trimmed;
        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://') || str_starts_with($candidate, '//')) {
            $candidate = $this->extractPathFromUrl($candidate);
            if ($candidate === null) {
                return null;
            }
        }

        $hashPos = strpos($candidate, '#');
        if ($hashPos !== false) {
            $candidate = substr($candidate, 0, $hashPos);
        }
        $queryPos = strpos($candidate, '?');
        if ($queryPos !== false) {
            $candidate = substr($candidate, 0, $queryPos);
        }

        $candidate = preg_replace('~/+~', '/', $candidate) ?? $candidate;

        if (str_starts_with($candidate, $this->uploadPrefix)) {
            $normalized = $candidate;
        } elseif (str_starts_with($candidate, ltrim($this->uploadPrefix, '/'))) {
            $normalized = '/' . ltrim($candidate, '/');
        } else {
            $pos = strpos($candidate, $this->uploadPrefix . '/');
            if ($pos === false) {
                $pos = strpos($candidate, $this->uploadPrefix);
            }
            if ($pos === false) {
                return null;
            }
            $normalized = substr($candidate, $pos);
        }

        $normalized = '/' . ltrim($normalized, '/');
        if (!str_starts_with($normalized, $this->uploadPrefix)) {
            return null;
        }

        return $normalized;
    }

    private function extractPathFromUrl(string $value): ?string
    {
        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        }
        $parts = parse_url($value);
        if ($parts === false) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (!is_string($path) || $path === '') {
            return null;
        }
        return $path;
    }

    private function fileExists(string $path): bool
    {
        $relative = $this->relativePath($path);
        if ($relative === null || $relative === '') {
            return false;
        }
        $absolute = rtrim($this->uploadRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return is_file($absolute);
    }

    private function relativePath(string $path): ?string
    {
        if (!str_starts_with($path, $this->uploadPrefix)) {
            return null;
        }
        $relative = substr($path, strlen($this->uploadPrefix));
        return ltrim((string) $relative, '/');
    }

    private function normalizePrefix(string $prefix): string
    {
        $trimmed = trim($prefix);
        if ($trimmed === '') {
            return '/uploads';
        }
        $normalized = '/' . ltrim($trimmed, '/');
        return rtrim($normalized, '/');
    }
}
