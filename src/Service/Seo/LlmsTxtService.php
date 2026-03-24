<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\CmsPageWikiArticle;
use App\Domain\LandingNews;
use App\Domain\Page;
use App\Service\CmsPageWikiArticleService;
use App\Service\LandingNewsService;
use App\Service\PageService;

/**
 * Generates llms.txt and llms-full.txt content per namespace/domain.
 *
 * The llms.txt standard (llmstxt.org) provides structured information
 * specifically for large language models to consume.
 */
final class LlmsTxtService
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private CmsPageWikiArticleService $wikiArticles;
    private LandingNewsService $landingNews;

    public function __construct(
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?CmsPageWikiArticleService $wikiArticles = null,
        ?LandingNewsService $landingNews = null
    ) {
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
        $this->wikiArticles = $wikiArticles ?? new CmsPageWikiArticleService();
        $this->landingNews = $landingNews ?? new LandingNewsService();
    }

    /**
     * Generate the short llms.txt overview with links.
     */
    public function generate(string $namespace, string $baseUrl): string
    {
        $pages = $this->getPublishedPages($namespace);
        $lines = [];

        $lines[] = '# ' . $this->resolveSiteName($pages, $namespace);
        $lines[] = '';
        $lines[] = '> ' . $this->resolveSiteDescription($pages, $namespace);
        $lines[] = '';

        $pageEntries = $this->buildPageEntries($pages, $baseUrl);
        if ($pageEntries !== []) {
            $lines[] = '## Seiten';
            $lines[] = '';
            foreach ($pageEntries as $entry) {
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        $wikiEntries = $this->buildWikiEntries($pages, $baseUrl);
        if ($wikiEntries !== []) {
            $lines[] = '## Wiki';
            $lines[] = '';
            foreach ($wikiEntries as $entry) {
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        $lines[] = '## Vollständige Inhalte';
        $lines[] = '';
        $lines[] = '- [llms-full.txt](' . $baseUrl . '/llms-full.txt)';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate the full llms-full.txt with all public content as Markdown.
     */
    public function generateFull(string $namespace, string $baseUrl): string
    {
        $pages = $this->getPublishedPages($namespace);
        $sections = [];

        $sections[] = '# ' . $this->resolveSiteName($pages, $namespace);
        $sections[] = '';
        $sections[] = '> ' . $this->resolveSiteDescription($pages, $namespace);
        $sections[] = '';

        foreach ($pages as $page) {
            $seoConfig = $this->seo->load($page->getId());
            $slug = $seoConfig !== null ? $seoConfig->getSlug() : $page->getSlug();
            $title = $seoConfig !== null && $seoConfig->getMetaTitle() !== null
                ? $seoConfig->getMetaTitle()
                : $page->getTitle();
            $description = $seoConfig !== null ? $seoConfig->getMetaDescription() : null;
            $url = $baseUrl . '/' . ltrim($slug, '/');

            $sections[] = '---';
            $sections[] = '';
            $sections[] = '## ' . $title;
            $sections[] = '';
            $sections[] = 'URL: ' . $url;

            if ($description !== null && $description !== '') {
                $sections[] = '';
                $sections[] = $description;
            }

            $pageContent = $this->extractPageContent($page);
            if ($pageContent !== '') {
                $sections[] = '';
                $sections[] = $pageContent;
            }

            $wikiArticles = $this->wikiArticles->getPublishedArticles($page->getId(), 'de');
            foreach ($wikiArticles as $article) {
                $sections[] = '';
                $sections[] = '### ' . $article->getTitle();
                if ($article->getExcerpt() !== null && $article->getExcerpt() !== '') {
                    $sections[] = '';
                    $sections[] = $article->getExcerpt();
                }
                $markdown = $article->getContentMarkdown();
                if ($markdown !== '') {
                    $sections[] = '';
                    $sections[] = $markdown;
                }
            }

            $newsItems = $this->landingNews->getPublishedForPage($page->getId(), 10);
            if ($newsItems !== []) {
                $sections[] = '';
                $sections[] = '### Neuigkeiten';
                foreach ($newsItems as $news) {
                    $sections[] = '';
                    $sections[] = '#### ' . $news->getTitle();
                    if ($news->getPublishedAt() !== null) {
                        $sections[] = '';
                        $sections[] = 'Veröffentlicht: ' . $news->getPublishedAt()->format('Y-m-d');
                    }
                    if ($news->getExcerpt() !== null && $news->getExcerpt() !== '') {
                        $sections[] = '';
                        $sections[] = $news->getExcerpt();
                    }
                    $content = $this->sanitizeHtmlToText($news->getContent());
                    if ($content !== '') {
                        $sections[] = '';
                        $sections[] = $content;
                    }
                }
            }

            $sections[] = '';
        }

        return implode("\n", $sections);
    }

    /**
     * @return Page[]
     */
    private function getPublishedPages(string $namespace): array
    {
        $allPages = $this->pages->getAllForNamespace($namespace);

        return array_values(array_filter(
            $allPages,
            static fn (Page $page): bool => $page->isPublished()
        ));
    }

    /**
     * @param Page[] $pages
     * @return string[]
     */
    private function buildPageEntries(array $pages, string $baseUrl): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $seoConfig = $this->seo->load($page->getId());
            $slug = $seoConfig !== null ? $seoConfig->getSlug() : $page->getSlug();
            $title = $seoConfig !== null && $seoConfig->getMetaTitle() !== null
                ? $seoConfig->getMetaTitle()
                : $page->getTitle();
            $description = $seoConfig !== null && $seoConfig->getMetaDescription() !== null
                ? $seoConfig->getMetaDescription()
                : '';
            $url = $baseUrl . '/' . ltrim($slug, '/');

            $entry = '- [' . $title . '](' . $url . ')';
            if ($description !== '') {
                $entry .= ': ' . $description;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param Page[] $pages
     * @return string[]
     */
    private function buildWikiEntries(array $pages, string $baseUrl): array
    {
        $entries = [];
        foreach ($pages as $page) {
            $articles = $this->wikiArticles->getPublishedArticles($page->getId(), 'de');
            $seoConfig = $this->seo->load($page->getId());
            $pageSlug = $seoConfig !== null ? $seoConfig->getSlug() : $page->getSlug();

            foreach ($articles as $article) {
                $url = $baseUrl . '/' . ltrim($pageSlug, '/') . '/wiki/' . $article->getSlug();
                $entry = '- [' . $article->getTitle() . '](' . $url . ')';
                if ($article->getExcerpt() !== null && $article->getExcerpt() !== '') {
                    $entry .= ': ' . $article->getExcerpt();
                }
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function extractPageContent(Page $page): string
    {
        $content = $page->getContent();
        if ($content === '') {
            return '';
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $this->sanitizeHtmlToText($content);
        }

        if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
            return $this->blocksToMarkdown($decoded['blocks']);
        }

        return '';
    }

    /**
     * @param array<int,array<string,mixed>> $blocks
     */
    private function blocksToMarkdown(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            switch ($type) {
                case 'header':
                    $level = max(1, min(6, (int) ($data['level'] ?? 3)));
                    $text = $this->sanitizeHtmlToText((string) ($data['text'] ?? ''));
                    if ($text !== '') {
                        $parts[] = str_repeat('#', $level) . ' ' . $text;
                    }
                    break;
                case 'paragraph':
                    $text = $this->sanitizeHtmlToText((string) ($data['text'] ?? ''));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                    break;
                case 'list':
                    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
                    foreach ($items as $i => $item) {
                        $text = $this->sanitizeHtmlToText(
                            is_array($item) ? (string) ($item['text'] ?? '') : (string) $item
                        );
                        if ($text !== '') {
                            $parts[] = '- ' . $text;
                        }
                    }
                    break;
                case 'quote':
                    $text = $this->sanitizeHtmlToText((string) ($data['text'] ?? ''));
                    if ($text !== '') {
                        $parts[] = '> ' . $text;
                    }
                    break;
            }
        }

        return implode("\n\n", $parts);
    }

    private function sanitizeHtmlToText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * @param Page[] $pages
     */
    private function resolveSiteName(array $pages, string $namespace): string
    {
        foreach ($pages as $page) {
            if ($page->isStartpage()) {
                $seo = $this->seo->load($page->getId());
                if ($seo !== null && $seo->getMetaTitle() !== null) {
                    return $seo->getMetaTitle();
                }

                return $page->getTitle();
            }
        }

        return ucfirst($namespace);
    }

    /**
     * @param Page[] $pages
     */
    private function resolveSiteDescription(array $pages, string $namespace): string
    {
        foreach ($pages as $page) {
            if ($page->isStartpage()) {
                $seo = $this->seo->load($page->getId());
                if ($seo !== null && $seo->getMetaDescription() !== null) {
                    return $seo->getMetaDescription();
                }
            }
        }

        return 'Inhalte von ' . ucfirst($namespace);
    }
}
