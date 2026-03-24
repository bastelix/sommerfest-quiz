<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Domain\CmsPageWikiArticle;
use App\Domain\LandingNews;
use App\Domain\Page;
use App\Service\CmsPageWikiArticleService;
use App\Service\LandingNewsService;
use App\Service\PageService;

/**
 * Generates RSS 2.0 and Atom feeds from published news and wiki articles.
 */
final class FeedService
{
    private PageService $pages;
    private CmsPageWikiArticleService $wikiArticles;
    private LandingNewsService $landingNews;

    public function __construct(
        ?PageService $pages = null,
        ?CmsPageWikiArticleService $wikiArticles = null,
        ?LandingNewsService $landingNews = null
    ) {
        $this->pages = $pages ?? new PageService();
        $this->wikiArticles = $wikiArticles ?? new CmsPageWikiArticleService();
        $this->landingNews = $landingNews ?? new LandingNewsService();
    }

    public function generateRss(string $namespace, string $baseUrl, string $siteName): string
    {
        $items = $this->collectItems($namespace, $baseUrl);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . $this->esc($siteName) . '</title>' . "\n";
        $xml .= '  <link>' . $this->esc($baseUrl) . '</link>' . "\n";
        $xml .= '  <description>' . $this->esc($siteName . ' – Neuigkeiten und Artikel') . '</description>' . "\n";
        $xml .= '  <language>de-de</language>' . "\n";
        $xml .= '  <atom:link href="' . $this->esc($baseUrl . '/feed.xml') . '"'
            . ' rel="self" type="application/rss+xml" />' . "\n";

        foreach ($items as $item) {
            $xml .= '  <item>' . "\n";
            $xml .= '    <title>' . $this->esc($item['title']) . '</title>' . "\n";
            $xml .= '    <link>' . $this->esc($item['url']) . '</link>' . "\n";
            $xml .= '    <guid isPermaLink="true">' . $this->esc($item['url']) . '</guid>' . "\n";
            if ($item['description'] !== '') {
                $xml .= '    <description>' . $this->esc($item['description']) . '</description>' . "\n";
            }
            if ($item['pubDate'] !== null) {
                $xml .= '    <pubDate>' . $item['pubDate'] . '</pubDate>' . "\n";
            }
            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    public function generateAtom(string $namespace, string $baseUrl, string $siteName): string
    {
        $items = $this->collectItems($namespace, $baseUrl);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <title>' . $this->esc($siteName) . '</title>' . "\n";
        $xml .= '  <link href="' . $this->esc($baseUrl) . '" />' . "\n";
        $xml .= '  <link href="' . $this->esc($baseUrl . '/feed.atom') . '" rel="self" />' . "\n";
        $xml .= '  <id>' . $this->esc($baseUrl . '/') . '</id>' . "\n";
        $xml .= '  <updated>' . gmdate('c') . '</updated>' . "\n";

        foreach ($items as $item) {
            $xml .= '  <entry>' . "\n";
            $xml .= '    <title>' . $this->esc($item['title']) . '</title>' . "\n";
            $xml .= '    <link href="' . $this->esc($item['url']) . '" />' . "\n";
            $xml .= '    <id>' . $this->esc($item['url']) . '</id>' . "\n";
            if ($item['updatedAt'] !== null) {
                $xml .= '    <updated>' . $item['updatedAt'] . '</updated>' . "\n";
            } else {
                $xml .= '    <updated>' . gmdate('c') . '</updated>' . "\n";
            }
            if ($item['description'] !== '') {
                $xml .= '    <summary>' . $this->esc($item['description']) . '</summary>' . "\n";
            }
            $xml .= '  </entry>' . "\n";
        }

        $xml .= '</feed>' . "\n";

        return $xml;
    }

    /**
     * @return array<int,array{title:string,url:string,description:string,pubDate:?string,updatedAt:?string}>
     */
    private function collectItems(string $namespace, string $baseUrl): array
    {
        $items = [];
        $publishedPages = array_filter(
            $this->pages->getAllForNamespace($namespace),
            static fn (Page $p): bool => $p->isPublished()
        );

        foreach ($publishedPages as $page) {
            // News articles
            $newsItems = $this->landingNews->getPublishedForPage($page->getId(), 20);
            foreach ($newsItems as $news) {
                $url = $baseUrl . '/' . $page->getSlug() . '/news/' . $news->getSlug();
                $items[] = [
                    'title' => $news->getTitle(),
                    'url' => $url,
                    'description' => $news->getExcerpt() ?? '',
                    'pubDate' => $news->getPublishedAt() !== null
                        ? $news->getPublishedAt()->format(\DateTimeInterface::RFC2822)
                        : null,
                    'updatedAt' => $news->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                    'timestamp' => $news->getPublishedAt() ?? $news->getUpdatedAt(),
                ];
            }

            // Wiki articles
            $wikiItems = $this->wikiArticles->getPublishedArticles($page->getId(), 'de');
            foreach ($wikiItems as $article) {
                $url = $baseUrl . '/' . $page->getSlug() . '/wiki/' . $article->getSlug();
                $items[] = [
                    'title' => $article->getTitle(),
                    'url' => $url,
                    'description' => $article->getExcerpt() ?? '',
                    'pubDate' => $article->getPublishedAt() !== null
                        ? $article->getPublishedAt()->format(\DateTimeInterface::RFC2822)
                        : null,
                    'updatedAt' => $article->getUpdatedAt() !== null
                        ? $article->getUpdatedAt()->format(\DateTimeInterface::ATOM)
                        : null,
                    'timestamp' => $article->getPublishedAt() ?? $article->getUpdatedAt(),
                ];
            }
        }

        // Sort by date descending
        usort($items, static function (array $a, array $b): int {
            $aTime = $a['timestamp'] ?? null;
            $bTime = $b['timestamp'] ?? null;
            if ($aTime === null && $bTime === null) {
                return 0;
            }
            if ($aTime === null) {
                return 1;
            }
            if ($bTime === null) {
                return -1;
            }

            return $bTime <=> $aTime;
        });

        // Remove internal timestamp field
        return array_map(static function (array $item): array {
            unset($item['timestamp']);
            return $item;
        }, array_slice($items, 0, 50));
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
