<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Application\Seo\PageSeoConfigService;
use App\Domain\Page;
use App\Service\CmsPageWikiArticleService;
use App\Service\PageService;

/**
 * Generates a dynamic XML sitemap from published CMS pages and wiki articles.
 */
final class SitemapService
{
    private PageService $pages;
    private PageSeoConfigService $seo;
    private CmsPageWikiArticleService $wikiArticles;

    public function __construct(
        ?PageService $pages = null,
        ?PageSeoConfigService $seo = null,
        ?CmsPageWikiArticleService $wikiArticles = null
    ) {
        $this->pages = $pages ?? new PageService();
        $this->seo = $seo ?? new PageSeoConfigService();
        $this->wikiArticles = $wikiArticles ?? new CmsPageWikiArticleService();
    }

    public function generate(string $namespace, string $baseUrl): string
    {
        $pages = $this->getPublishedPages($namespace);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= $this->urlEntry($baseUrl . '/', 'daily', '1.0');

        foreach ($pages as $page) {
            $seoConfig = $this->seo->load($page->getId());
            $slug = $seoConfig !== null ? $seoConfig->getSlug() : $page->getSlug();
            $url = $baseUrl . '/' . ltrim($slug, '/');
            $xml .= $this->urlEntry($url, 'weekly', '0.8');

            // Wiki articles for this page
            $articles = $this->wikiArticles->getPublishedArticles($page->getId(), 'de');
            foreach ($articles as $article) {
                $articleUrl = $baseUrl . '/' . ltrim($slug, '/') . '/wiki/' . $article->getSlug();
                $lastmod = $article->getUpdatedAt() ?? $article->getPublishedAt();
                $xml .= $this->urlEntry(
                    $articleUrl,
                    'monthly',
                    '0.6',
                    $lastmod !== null ? $lastmod->format('Y-m-d') : null
                );
            }
        }

        // Static pages
        $staticPages = ['faq', 'datenschutz', 'impressum', 'lizenz'];
        foreach ($staticPages as $staticSlug) {
            $xml .= $this->urlEntry($baseUrl . '/' . $staticSlug, 'monthly', '0.4');
        }

        // llms.txt endpoints
        $xml .= $this->urlEntry($baseUrl . '/llms.txt', 'weekly', '0.3');

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * @return Page[]
     */
    private function getPublishedPages(string $namespace): array
    {
        return array_values(array_filter(
            $this->pages->getAllForNamespace($namespace),
            static fn (Page $page): bool => $page->isPublished()
        ));
    }

    private function urlEntry(string $loc, string $changefreq, string $priority, ?string $lastmod = null): string
    {
        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . "\n";
        if ($lastmod !== null) {
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        }
        $xml .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $priority . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        return $xml;
    }
}
