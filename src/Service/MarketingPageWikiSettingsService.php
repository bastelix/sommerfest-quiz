<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\MarketingPageWikiSettings;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class MarketingPageWikiSettingsService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function getSettingsForPage(int $pageId): MarketingPageWikiSettings
    {
        $stmt = $this->pdo->prepare(
            'SELECT page_id, is_active, menu_label, menu_labels, updated_at '
            . 'FROM marketing_page_wiki_settings WHERE page_id = ?'
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return new MarketingPageWikiSettings($pageId, false, null, [], null);
        }

        $updatedAt = null;
        if (isset($row['updated_at'])) {
            $updatedAt = new DateTimeImmutable((string) $row['updated_at']);
        }

        $menuLabels = $this->normalizeMenuLabels($row['menu_labels'] ?? null);

        return new MarketingPageWikiSettings(
            (int) $row['page_id'],
            (bool) $row['is_active'],
            isset($row['menu_label']) ? (string) $row['menu_label'] : null,
            $menuLabels,
            $updatedAt
        );
    }

    /**
     * @param array<string, string>|null $menuLabels
     */
    public function updateSettings(
        int $pageId,
        bool $isActive,
        ?string $menuLabel = null,
        ?array $menuLabels = null
    ): MarketingPageWikiSettings {
        $normalizedLabel = $menuLabel !== null ? trim($menuLabel) : null;
        if ($normalizedLabel !== null && $normalizedLabel !== '' && mb_strlen($normalizedLabel) > 64) {
            throw new RuntimeException('Menu label must not exceed 64 characters.');
        }

        $normalizedLabels = $this->sanitizeMenuLabels($menuLabels ?? []);

        $stmt = $this->pdo->prepare('SELECT page_id FROM marketing_page_wiki_settings WHERE page_id = ?');
        $stmt->execute([$pageId]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

        if ($exists) {
            $update = $this->pdo->prepare(
                'UPDATE marketing_page_wiki_settings SET is_active = ?, menu_label = ?, menu_labels = ?, '
                . 'updated_at = CURRENT_TIMESTAMP WHERE page_id = ?'
            );
            $update->execute([
                $isActive ? 1 : 0,
                $normalizedLabel,
                json_encode($normalizedLabels, JSON_THROW_ON_ERROR),
                $pageId,
            ]);
        } else {
            $insert = $this->pdo->prepare(
                'INSERT INTO marketing_page_wiki_settings (page_id, is_active, menu_label, menu_labels) '
                . 'VALUES (?, ?, ?, ?)'
            );
            $insert->execute([
                $pageId,
                $isActive ? 1 : 0,
                $normalizedLabel,
                json_encode($normalizedLabels, JSON_THROW_ON_ERROR),
            ]);
        }

        return $this->getSettingsForPage($pageId);
    }

    /**
     * @return array<string, string>
     */
    private function normalizeMenuLabels(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $this->sanitizeMenuLabels($value);
        }

        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $this->sanitizeMenuLabels($decoded);
    }

    /**
     * @param array<mixed, mixed> $rawLabels
     * @return array<string, string>
     */
    private function sanitizeMenuLabels(array $rawLabels): array
    {
        $normalized = [];
        foreach ($rawLabels as $locale => $label) {
            if (!is_string($locale)) {
                continue;
            }
            if (!is_string($label)) {
                continue;
            }
            $localeKey = strtolower(trim($locale));
            if ($localeKey === '') {
                continue;
            }
            $labelValue = trim($label);
            if ($labelValue === '') {
                continue;
            }
            if (mb_strlen($labelValue) > 64) {
                throw new RuntimeException('Menu label must not exceed 64 characters.');
            }
            $normalized[$localeKey] = $labelValue;
        }

        return $normalized;
    }
}
