<?php

declare(strict_types=1);

namespace App\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use DOMDocument;
use RuntimeException;

/**
 * Aggregates landing page media references from markup and SEO configuration.
 */
class LandingMediaReferenceService
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private ConfigService $config;

    /** @var list<string> */
    private const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    private const TYPE_MARKUP = 'markup';
    private const TYPE_SEO = 'seo';

    public function __construct(PageService $pages, PageSeoConfigService $seo, ConfigService $config) {
        $this->pages = $pages;
        $this->seo = $seo;
        $this->config = $config;
    }

    /**
     * Return landing page slugs with titles.
     *
     * @return list<array{slug:string,title:string}>
     */
    public function getLandingSlugs(): array {
        $slugs = [];
        foreach ($this->getLandingPages() as $page) {
            $slugs[] = [
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
            ];
        }

        return $slugs;
    }

    /**
     * Collect landing page media references grouped by normalized path.
     *
     * @return array{
     *     slugs:list<array{slug:string,title:string}>,
     *     files:array<string,list<array<string,mixed>>>,
     *     missing:list<array<string,mixed>>
     * }
     */
    public function collect(): array {
        $landingPages = $this->getLandingPages();
        $slugs = [];
        $files = [];
        $missing = [];
        $seenMissing = [];

        foreach ($landingPages as $page) {
            $slug = $page->getSlug();
            $title = $page->getTitle();
            $slugs[] = ['slug' => $slug, 'title' => $title];

            $references = $this->collectFromMarkup($page);

            $seoConfig = $this->seo->load($page->getId());
            if ($seoConfig instanceof PageSeoConfig) {
                $references = array_merge($references, $this->collectFromSeo($page, $seoConfig));
            }

            foreach ($references as $reference) {
                $path = $reference['path'] ?? null;
                if (!is_string($path) || $path === '') {
                    continue;
                }
                if (!isset($files[$path])) {
                    $files[$path] = [];
                }

                if (!$this->referenceExists($files[$path], $reference)) {
                    $files[$path][] = $reference;
                }

                $absolute = $this->resolveAbsolutePath($path);
                if (!is_file($absolute)) {
                    $missingKey = sprintf('%s|%s|%s|%s', $reference['slug'] ?? '', $path, $reference['type'] ?? '', $reference['field'] ?? '');
                    if (!isset($seenMissing[$missingKey])) {
                        $missing[] = $this->buildMissingEntry($reference, $path);
                        $seenMissing[$missingKey] = true;
                    }
                }
            }
        }

        return [
            'slugs' => $slugs,
            'files' => $files,
            'missing' => $missing,
        ];
    }

    /**
     * Normalize a public upload URL or path to the canonical internal representation.
     */
    public function normalizeFilePath(?string $path): ?string {
        if ($path === null) {
            return null;
        }

        $normalized = $this->sanitizeUploadPath($path);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return Page[]
     */
    private function getLandingPages(): array {
        $pages = $this->pages->getAll();

        return array_values(array_filter(
            $pages,
            static function (Page $page): bool {
                $slug = $page->getSlug();
                if ($slug === '') {
                    return false;
                }
                return !in_array($slug, self::EXCLUDED_SLUGS, true);
            }
        ));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function collectFromMarkup(Page $page): array {
        $content = $page->getContent();
        if ($content === '') {
            return [];
        }

        $altMap = $this->collectImageAltMap($content);

        $matches = [];
        preg_match_all('~(?:\{\{\s*basePath\s*\}\}\s*)?/?uploads/[A-Za-z0-9_@./-]+~i', $content, $matches);
        if (!isset($matches[0])) {
            return [];
        }

        $references = [];
        foreach ($matches[0] as $raw) {
            $normalized = $this->sanitizeUploadPath((string) $raw);
            if ($normalized === '') {
                continue;
            }
            $reference = [
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'path' => $normalized,
                'type' => self::TYPE_MARKUP,
                'field' => 'content',
            ];
            $altText = $altMap[$normalized] ?? null;
            if (is_string($altText) && $altText !== '') {
                $reference['alt'] = $altText;
            }
            $references[] = $reference;
        }

        return $this->deduplicateReferences($references);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function collectFromSeo(Page $page, PageSeoConfig $config): array {
        $references = [];
        $fields = [
            'ogImage' => $config->getOgImage(),
            'faviconPath' => $config->getFaviconPath(),
        ];

        foreach ($fields as $field => $value) {
            if ($value === null) {
                continue;
            }
            $normalized = $this->sanitizeUploadPath($value);
            if ($normalized === '') {
                continue;
            }
            $references[] = [
                'slug' => $page->getSlug(),
                'title' => $page->getTitle(),
                'path' => $normalized,
                'type' => self::TYPE_SEO,
                'field' => $field,
            ];
        }

        return $this->deduplicateReferences($references);
    }

    /**
     * @param list<array<string,mixed>> $references
     * @return list<array<string,mixed>>
     */
    private function deduplicateReferences(array $references): array {
        $unique = [];
        $seen = [];

        foreach ($references as $reference) {
            $key = json_encode([
                $reference['slug'] ?? '',
                $reference['path'] ?? '',
                $reference['type'] ?? '',
                $reference['field'] ?? '',
            ]);
            if ($key === false || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $reference;
        }

        return $unique;
    }

    /**
     * @param list<array<string,mixed>> $existing
     * @param array<string,mixed> $candidate
     */
    private function referenceExists(array $existing, array $candidate): bool {
        foreach ($existing as $reference) {
            if (
                ($reference['slug'] ?? null) === ($candidate['slug'] ?? null)
                && ($reference['type'] ?? null) === ($candidate['type'] ?? null)
                && ($reference['field'] ?? null) === ($candidate['field'] ?? null)
            ) {
                return true;
            }
        }

        return false;
    }

    private function resolveAbsolutePath(string $normalized): string {
        if (!str_starts_with($normalized, 'uploads/')) {
            throw new RuntimeException('invalid upload path: ' . $normalized);
        }

        $relative = substr($normalized, strlen('uploads/'));
        $relative = ltrim($relative, '/');
        $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

        return $this->config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . $relative;
    }

    /**
     * @param array<string,mixed> $reference
     * @return array<string,mixed>
     */
    private function buildMissingEntry(array $reference, string $path): array {
        $folder = $this->extractFolder($path);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $reference['path'] = $path;
        $reference['displayPath'] = '/' . ltrim($path, '/');
        $reference['suggestedName'] = is_string($name) ? $name : null;
        $reference['suggestedFolder'] = $folder;
        $reference['extension'] = is_string($extension) ? strtolower($extension) : null;

        return $reference;
    }

    /**
     * Extract image alt texts keyed by their normalized upload paths.
     *
     * @return array<string,string>
     */
    private function collectImageAltMap(string $content): array {
        if ($content === '') {
            return [];
        }

        $map = [];
        $previous = libxml_use_internal_errors(true);

        $document = new DOMDocument();
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $content . '</body></html>';
        if ($document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            foreach ($document->getElementsByTagName('img') as $image) {
                $src = $image->getAttribute('src');
                $alt = trim($image->getAttribute('alt'));
                if ($src === '' || $alt === '') {
                    continue;
                }
                $normalized = $this->sanitizeUploadPath($src);
                if ($normalized === '') {
                    continue;
                }
                $map[$normalized] = $alt;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $map;
    }

    private function extractFolder(string $path): ?string {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) <= 2) {
            return null;
        }
        array_shift($segments); // remove "uploads"
        array_pop($segments);   // remove filename
        if ($segments === []) {
            return null;
        }
        return implode('/', $segments);
    }

    private function sanitizeUploadPath(string $value): string {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $trimmed = preg_replace('~\{\{\s*basePath\s*\}\}~', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        if (preg_match('~^[a-z][a-z0-9+\-.]*:~i', $trimmed)) {
            return '';
        }

        if (str_starts_with($trimmed, '//')) {
            return '';
        }

        if (preg_match('~https?://[^/]+(/.*)$~i', $trimmed, $match)) {
            $trimmed = (string) ($match[1] ?? '');
        }

        $trimmed = ltrim($trimmed);
        if (str_starts_with($trimmed, '/')) {
            $trimmed = ltrim($trimmed, '/');
        }

        if (!str_starts_with($trimmed, 'uploads/')) {
            $pos = stripos($trimmed, 'uploads/');
            if ($pos === false) {
                return '';
            }
            $trimmed = substr($trimmed, $pos);
        }

        $trimmed = preg_split('/[?#]/', $trimmed)[0] ?? $trimmed;
        $trimmed = preg_replace('~/+~', '/', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }
}
