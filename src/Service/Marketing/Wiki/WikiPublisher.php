<?php

declare(strict_types=1);

namespace App\Service\Marketing\Wiki;

use App\Domain\CmsPageWikiArticle;
use DateTimeImmutable;
use RuntimeException;

final class WikiPublisher
{
    private string $contentRoot;

    public function __construct(?string $contentRoot = null)
    {
        $defaultRoot = dirname(__DIR__, 4) . '/content/pages';
        $this->contentRoot = rtrim($contentRoot ?? $defaultRoot, '/');
    }

    public function publish(string $pageSlug, CmsPageWikiArticle $article): void
    {
        if (!$article->isPublished()) {
            return;
        }

        $directory = sprintf('%s/%s/%s/wiki', $this->contentRoot, $article->getLocale(), $pageSlug);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create wiki directory: %s', $directory));
        }

        $indexPath = $directory . '/index.json';
        $index = $this->readIndex($indexPath);
        $index[$article->getSlug()] = [
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'locale' => $article->getLocale(),
            'excerpt' => $article->getExcerpt(),
            'updated_at' => $article->getUpdatedAt()?->format(DateTimeImmutable::ATOM),
            'published_at' => $article->getPublishedAt()?->format(DateTimeImmutable::ATOM),
            'status' => $article->getStatus(),
        ];
        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $frontMatter = [
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'locale' => $article->getLocale(),
            'published_at' => $article->getPublishedAt()?->format(DateTimeImmutable::ATOM),
            'updated_at' => $article->getUpdatedAt()?->format(DateTimeImmutable::ATOM),
            'status' => $article->getStatus(),
        ];

        $frontMatterLines = ["---"];
        foreach ($frontMatter as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $frontMatterLines[] = sprintf('%s: "%s"', $key, str_replace('"', '\"', (string) $value));
        }
        $frontMatterLines[] = '---';

        $markdownPath = sprintf('%s/%s.md', $directory, $article->getSlug());
        file_put_contents($markdownPath, implode("\n", $frontMatterLines) . "\n" . $article->getContentMarkdown() . "\n");
    }

    public function remove(string $pageSlug, string $locale, string $articleSlug): void
    {
        $directory = sprintf('%s/%s/%s/wiki', $this->contentRoot, $locale, $pageSlug);
        $markdownPath = sprintf('%s/%s.md', $directory, $articleSlug);
        if (is_file($markdownPath)) {
            unlink($markdownPath);
        }

        $indexPath = $directory . '/index.json';
        if (!is_file($indexPath)) {
            return;
        }

        $index = $this->readIndex($indexPath);
        unset($index[$articleSlug]);
        file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function readIndex(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
