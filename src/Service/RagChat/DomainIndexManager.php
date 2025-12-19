<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use App\Domain\MarketingPageWikiArticle;
use App\Service\MarketingPageWikiArticleService;
use InvalidArgumentException;
use RuntimeException;

use function App\runSyncProcess;
use function array_fill_keys;
use function file_put_contents;
use function glob;
use function implode;
use function is_file;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;
use function unlink;

final class DomainIndexManager
{
    private DomainDocumentStorage $storage;

    private string $projectRoot;

    private string $pythonBinary;

    private ?DomainWikiSelectionService $wikiSelection;

    private ?MarketingPageWikiArticleService $wikiArticles;

    public function __construct(
        DomainDocumentStorage $storage,
        ?string $projectRoot = null,
        string $pythonBinary = 'python3',
        ?DomainWikiSelectionService $wikiSelection = null,
        ?MarketingPageWikiArticleService $wikiArticles = null
    ) {
        $this->storage = $storage;
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->pythonBinary = $pythonBinary;
        $this->wikiSelection = $wikiSelection;
        $this->wikiArticles = $wikiArticles;
    }

    /**
     * @return array{success:bool,stdout:string,stderr:string,cleared:bool}
     */
    public function rebuild(string $domain): array
    {
        try {
            $normalized = $this->storage->normaliseDomain($domain);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid domain supplied.', 0, $exception);
        }

        $uploadsDir = $this->storage->getUploadsDirectory($normalized);
        $documents = $this->storage->getDocumentFiles($normalized);
        $wikiDocuments = $this->prepareWikiDocuments($normalized, $uploadsDir);
        if ($documents === [] && $wikiDocuments === []) {
            $this->storage->removeIndex($normalized);

            return [
                'success' => true,
                'stdout' => 'No documents available – cleared domain index.',
                'stderr' => '',
                'cleared' => true,
            ];
        }

        $allDocuments = array_merge($documents, $wikiDocuments);

        $corpusPath = $this->storage->getCorpusPath($normalized);
        $indexPath = $this->storage->getIndexPath($normalized);
        $this->ensureDirectory(dirname($corpusPath));

        $script = $this->projectRoot . '/scripts/rag_pipeline.py';
        if (!is_file($script)) {
            throw new RuntimeException('Pipeline script is missing.');
        }

        $args = [
            $script,
            $uploadsDir,
            '--corpus',
            $corpusPath,
            '--index',
            $indexPath,
            '--force',
        ];

        $result = runSyncProcess($this->pythonBinary, $args, false, $this->projectRoot);
        $result['cleared'] = false;

        if ($result['success'] !== true) {
            $stderr = trim($result['stderr']);
            $stdout = trim($result['stdout']);
            $message = $stderr !== '' ? $stderr : $stdout;
            if ($message === '') {
                $message = 'Domain index rebuild failed.';
            }

            throw new RuntimeException($message);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function prepareWikiDocuments(string $domain, string $uploadsDir): array
    {
        if ($this->wikiSelection === null || $this->wikiArticles === null) {
            return [];
        }

        $existingPattern = $uploadsDir . DIRECTORY_SEPARATOR . 'wiki-*.md';
        $existingFiles = glob($existingPattern) ?: [];

        $articleIds = $this->wikiSelection->getSelectedArticleIds($domain);
        if ($articleIds === []) {
            $this->cleanupWikiFiles($existingFiles, []);

            return [];
        }

        $this->ensureDirectory($uploadsDir);

        $writtenFiles = [];
        foreach ($articleIds as $articleId) {
            $article = $this->wikiArticles->getArticleById($articleId);
            if ($article === null || !$article->isPublished()) {
                continue;
            }

            $writtenFiles[] = $this->writeWikiDocument($uploadsDir, $domain, $article);
        }

        $this->cleanupWikiFiles($existingFiles, $writtenFiles);

        return $writtenFiles;
    }

    private function writeWikiDocument(string $uploadsDir, string $domain, MarketingPageWikiArticle $article): string
    {
        $slug = $this->sanitizeSlug($article->getSlug());
        $filename = sprintf(
            'wiki-%06d-%s-%s.md',
            $article->getId(),
            strtolower($article->getLocale()),
            $slug
        );
        $path = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

        $content = $this->renderWikiMarkdown($domain, $article);
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Failed to export wiki article %d for domain %s.', $article->getId(), $domain));
        }

        return $path;
    }

    /**
     * @param list<string> $existingFiles
     * @param list<string> $writtenFiles
     */
    private function cleanupWikiFiles(array $existingFiles, array $writtenFiles): void
    {
        if ($existingFiles === []) {
            return;
        }

        $keep = array_fill_keys($writtenFiles, true);
        foreach ($existingFiles as $file) {
            if (!isset($keep[$file]) && is_file($file)) {
                unlink($file);
            }
        }
    }

    private function renderWikiMarkdown(string $domain, MarketingPageWikiArticle $article): string
    {
        $title = trim($article->getTitle());
        $excerpt = $article->getExcerpt();
        $excerptText = $excerpt !== null ? trim($excerpt) : '';
        $content = trim($article->getContentMarkdown());

        if ($title === '' && $excerptText === '' && $content === '') {
            return '';
        }

        $lines = [];
        if ($title !== '') {
            $lines[] = '# ' . $title;
        }

        $meta = sprintf(
            '> Domain: %s | Locale: %s | Slug: %s',
            $domain,
            $article->getLocale(),
            $article->getSlug()
        );
        $lines[] = $meta;

        if ($excerptText !== '') {
            $lines[] = '';
            $lines[] = $excerptText;
        }

        if ($content !== '') {
            $lines[] = '';
            $lines[] = $content;
        }

        return implode("\n", $lines) . "\n";
    }

    private function sanitizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-') ?: 'article';
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
        }
    }
}
