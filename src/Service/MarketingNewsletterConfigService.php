<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Provides read/write access to marketing newsletter CTA configurations.
 */
class MarketingNewsletterConfigService
{
    public const STYLE_PRIMARY = 'primary';
    public const STYLE_SECONDARY = 'secondary';
    public const STYLE_LINK = 'link';

    /**
     * @var list<string>
     */
    private const ALLOWED_STYLES = [
        self::STYLE_PRIMARY,
        self::STYLE_SECONDARY,
        self::STYLE_LINK,
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Return ordered CTA entries for a marketing slug.
     *
     * @return list<array{label:string,url:string,style:string}>
     */
    public function getCtasForSlug(string $slug): array
    {
        $normalized = $this->normalizeSlug($slug);
        if ($normalized === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT label, url, style FROM marketing_newsletter_configs WHERE slug = :slug'
            . ' ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['slug' => $normalized]);

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'label' => (string) $row['label'],
                'url' => (string) $row['url'],
                'style' => $this->normalizeStyle($row['style'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * Fetch all CTA entries.
     *
     * @return list<array{id:int,slug:string,position:int,label:string,url:string,style:string}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, slug, position, label, url, style'
            . ' FROM marketing_newsletter_configs ORDER BY slug ASC, position ASC, id ASC'
        );

        $entries = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entries[] = [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'position' => (int) $row['position'],
                'label' => (string) $row['label'],
                'url' => (string) $row['url'],
                'style' => $this->normalizeStyle($row['style'] ?? null),
            ];
        }

        return $entries;
    }

    /**
     * Fetch CTA entries grouped by slug.
     *
     * @return array<string,list<array{id:int,position:int,label:string,url:string,style:string}>>
     */
    public function getAllGrouped(): array
    {
        $grouped = [];
        foreach ($this->getAll() as $entry) {
            $slug = $this->normalizeSlug($entry['slug']);
            if (!isset($grouped[$slug])) {
                $grouped[$slug] = [];
            }
            $grouped[$slug][] = [
                'id' => $entry['id'],
                'position' => $entry['position'],
                'label' => $entry['label'],
                'url' => $entry['url'],
                'style' => $entry['style'],
            ];
        }

        return $grouped;
    }

    /**
     * Replace CTA entries for a slug with the supplied list.
     *
     * @param list<array{label?:string,url?:string,style?:string}> $entries
     */
    public function saveEntries(string $slug, array $entries): void
    {
        $normalized = $this->normalizeSlug($slug);
        if ($normalized === '') {
            throw new RuntimeException('Slug is required for newsletter CTA configuration.');
        }

        $items = [];
        foreach ($entries as $entry) {
            if (!array_key_exists('label', $entry) || !array_key_exists('url', $entry)) {
                continue;
            }

            $label = trim((string) $entry['label']);
            $url = trim((string) $entry['url']);
            if ($label === '' || $url === '') {
                continue;
            }
            $items[] = [
                'label' => $label,
                'url' => $url,
                'style' => $this->normalizeStyle($entry['style'] ?? null),
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare('DELETE FROM marketing_newsletter_configs WHERE slug = :slug');
            $delete->execute(['slug' => $normalized]);

            if ($items !== []) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO marketing_newsletter_configs (slug, position, label, url, style)'
                    . ' VALUES (:slug, :position, :label, :url, :style)'
                );

                foreach ($items as $index => $item) {
                    $insert->execute([
                        'slug' => $normalized,
                        'position' => $index,
                        'label' => $item['label'],
                        'url' => $item['url'],
                        'style' => $item['style'],
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Failed to persist marketing newsletter configuration.', 0, $exception);
        }
    }

    /**
     * @return list<string>
     */
    public function getAllowedStyles(): array
    {
        return self::ALLOWED_STYLES;
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function normalizeStyle(?string $style): string
    {
        $normalized = strtolower(trim((string) $style));
        if ($normalized === '') {
            return self::STYLE_PRIMARY;
        }

        return in_array($normalized, self::ALLOWED_STYLES, true)
            ? $normalized
            : self::STYLE_PRIMARY;
    }
}
