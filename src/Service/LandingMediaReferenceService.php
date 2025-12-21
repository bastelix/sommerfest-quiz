<?php

declare(strict_types=1);

namespace App\Service;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\LandingNews;
use App\Domain\Page;
use App\Domain\PageSeoConfig;
use DOMDocument;
use RuntimeException;

/**
 * Aggregates landing page media references from markup and SEO configuration.
 *
 * @phpstan-type MediaReference array{
 *     slug:string,
 *     title:string,
 *     path:string,
 *     type:string,
 *     field:string,
 *     alt?:string
 * }
 * @phpstan-type MissingMediaReference array{
 *     slug?:string,
 *     title?:string,
 *     type?:string,
 *     field?:string,
 *     alt?:string,
 *     path:string,
 *     displayPath:string,
 *     suggestedName:?string,
 *     suggestedFolder:?string,
 *     extension:?string
 * }
 */
class LandingMediaReferenceService
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private ConfigService $config;

    private LandingNewsService $news;

    /** @var list<string> */
    private const EXCLUDED_SLUGS = ['impressum', 'datenschutz', 'faq', 'lizenz'];

    private const TYPE_MARKUP = 'markup';
    private const TYPE_SEO = 'seo';

    public function __construct(
        PageService $pages,
        PageSeoConfigService $seo,
        ConfigService $config,
        ?LandingNewsService $news = null
    ) {
        $this->pages = $pages;
        $this->seo = $seo;
        $this->config = $config;
        $this->news = $news ?? new LandingNewsService();
    }

    /**
     * Return landing page slugs with titles.
     *
     * @return list<array{slug:string,title:string}>
     */
    public function getLandingSlugs(?string $namespace = null): array {
        $slugs = [];
        foreach ($this->getLandingPages($namespace) as $page) {
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
     *     files:array<string,list<MediaReference>>,
     *     missing:list<MissingMediaReference>
     * }
     */
    public function collect(?string $namespace = null): array {
        $landingPages = $this->getLandingPages($namespace);
        /** @var list<array{slug:string,title:string}> $slugs */
        $slugs = [];
        /** @var array<string, list<MediaReference>> $files */
        $files = [];
        /** @var list<MissingMediaReference> $missing */
        $missing = [];
        /** @var array<string, true> $seenMissing */
        $seenMissing = [];

        foreach ($landingPages as $page) {
            $slug = $page->getSlug();
            $title = $page->getTitle();
            $slugs[] = ['slug' => $slug, 'title' => $title];

            $references = $this->collectFromMarkup($page);

            $newsReferences = $this->collectFromNews($page);
            if ($newsReferences !== []) {
                $references = array_merge($references, $newsReferences);
            }

            $seoConfig = $this->seo->load($page->getId());
            if ($seoConfig instanceof PageSeoConfig) {
                $references = array_merge($references, $this->collectFromSeo($page, $seoConfig));
            }

            foreach ($references as $reference) {
                $path = $reference['path'];
                if ($path === '') {
                    continue;
                }
                if (!array_key_exists($path, $files)) {
                    $files[$path] = [];
                }

                /** @var list<MediaReference> $pathReferences */
                $pathReferences = $files[$path];
                if (!$this->referenceExists($pathReferences, $reference)) {
                    $pathReferences[] = $reference;
                    $files[$path] = $pathReferences;
                }

                $absolute = $this->resolveAbsolutePath($path, $namespace);
                if (!is_file($absolute)) {
                    $missingKey = sprintf(
                        '%s|%s|%s|%s',
                        $reference['slug'],
                        $path,
                        $reference['type'],
                        $reference['field']
                    );
                    if (!isset($seenMissing[$missingKey])) {
                        $missing[] = $this->buildMissingEntry($reference, $path, $namespace);
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
    private function getLandingPages(?string $namespace = null): array {
        $pages = $namespace !== null && $namespace !== ''
            ? $this->pages->getAllForNamespace($namespace)
            : $this->pages->getAll();

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
     * @return list<MediaReference>
     */
    private function collectFromMarkup(Page $page): array {
        return $this->collectMarkupReferences(
            $page->getContent(),
            $page->getSlug(),
            $page->getTitle(),
            'content'
        );
    }

    /**
     * @return list<MediaReference>
     */
    private function collectFromNews(Page $page): array {
        $entries = $this->news->getAllForPage($page->getId());
        if ($entries === []) {
            return [];
        }

        /** @var list<MediaReference> $references */
        $references = [];

        foreach ($entries as $entry) {
            $references = array_merge(
                $references,
                $this->collectMarkupReferences(
                    $entry->getContent(),
                    $page->getSlug(),
                    $page->getTitle(),
                    sprintf('news:%s:content', $entry->getSlug())
                )
            );

            $excerpt = $entry->getExcerpt();
            if ($excerpt !== null && $excerpt !== '') {
                $references = array_merge(
                    $references,
                    $this->collectMarkupReferences(
                        $excerpt,
                        $page->getSlug(),
                        $page->getTitle(),
                        sprintf('news:%s:excerpt', $entry->getSlug())
                    )
                );
            }
        }

        return $references;
    }

    /**
     * @return list<MediaReference>
     */
    private function collectMarkupReferences(string $html, string $slug, string $title, string $field): array {
        $content = trim($html);
        if ($content === '') {
            return [];
        }

        $altMap = $this->collectImageAltMap($content);

        $matches = [];
        preg_match_all('~(?:\{\{\s*basePath\s*\}\}\s*)?/?uploads/[A-Za-z0-9_@./-]+~i', $content, $matches);
        if ($matches[0] === []) {
            return [];
        }

        /** @var list<MediaReference> $references */
        $references = [];
        foreach ($matches[0] as $raw) {
            $normalized = $this->sanitizeUploadPath((string) $raw);
            if ($normalized === '') {
                continue;
            }
            $reference = [
                'slug' => $slug,
                'title' => $title,
                'path' => $normalized,
                'type' => self::TYPE_MARKUP,
                'field' => $field,
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
     * @return list<MediaReference>
     */
    private function collectFromSeo(Page $page, PageSeoConfig $config): array {
        /** @var list<MediaReference> $references */
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
     * @param list<MediaReference> $references
     * @return list<MediaReference>
     */
    private function deduplicateReferences(array $references): array {
        $unique = [];
        $seen = [];

        foreach ($references as $reference) {
            $key = json_encode([
                $reference['slug'],
                $reference['path'],
                $reference['type'],
                $reference['field'],
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
     * @param list<MediaReference> $existing
     * @param MediaReference $candidate
     */
    private function referenceExists(array $existing, array $candidate): bool {
        foreach ($existing as $reference) {
            if (
                $reference['slug'] === $candidate['slug']
                && $reference['type'] === $candidate['type']
                && $reference['field'] === $candidate['field']
            ) {
                return true;
            }
        }

        return false;
    }

    private function resolveAbsolutePath(string $normalized, ?string $namespace = null): string {
        if (!str_starts_with($normalized, 'uploads/')) {
            throw new RuntimeException('invalid upload path: ' . $normalized);
        }

        $relative = substr($normalized, strlen('uploads/'));
        $relative = ltrim($relative, '/');

        if ($namespace !== null && $namespace !== '') {
            $normalizedNamespace = $this->normalizeNamespace($namespace);
            $projectPrefix = $normalizedNamespace !== '' ? 'projects/' . $normalizedNamespace . '/' : '';
            if ($projectPrefix !== '' && str_starts_with($relative, $projectPrefix)) {
                $relative = substr($relative, strlen($projectPrefix));
                $relative = ltrim($relative, '/');
                $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

                return $this->config->getProjectUploadsDir($normalizedNamespace) . DIRECTORY_SEPARATOR . $relative;
            }
        }

        $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

        return $this->config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . $relative;
    }

    /**
     * @param MediaReference $reference
     * @return MissingMediaReference
     */
    private function buildMissingEntry(array $reference, string $path, ?string $namespace): array {
        $folder = $this->extractFolder($path, $namespace);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $reference['path'] = $path;
        $reference['displayPath'] = '/' . ltrim($path, '/');
        $reference['suggestedName'] = $name !== '' ? $name : null;
        $reference['suggestedFolder'] = $folder;
        $reference['extension'] = $extension !== '' ? strtolower($extension) : null;

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

    private function extractFolder(string $path, ?string $namespace = null): ?string {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) <= 2) {
            return null;
        }
        array_shift($segments); // remove "uploads"
        if (isset($segments[0]) && $segments[0] === 'projects') {
            array_shift($segments);
        }
        $normalizedNamespace = $namespace !== null ? $this->normalizeNamespace($namespace) : '';
        if ($normalizedNamespace !== '' && isset($segments[0]) && $segments[0] === $normalizedNamespace) {
            array_shift($segments);
        }
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

        if (preg_match('~https?://[^/]+(/.*)$~i', $trimmed, $match) === 1) {
            $trimmed = (string) $match[1];
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

        $segments = preg_split('/[?#]/', $trimmed);
        if (is_array($segments) && $segments !== []) {
            $trimmed = (string) $segments[0];
        }
        $trimmed = preg_replace('~/+~', '/', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }

    private function normalizeNamespace(string $namespace): string
    {
        $validator = new NamespaceValidator();
        $normalized = $validator->normalizeCandidate($namespace);
        if ($normalized === null) {
            return '';
        }

        return $normalized;
    }
}
