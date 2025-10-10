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
        $stmt = $this->pdo->prepare('SELECT page_id, is_active, menu_label, updated_at FROM marketing_page_wiki_settings WHERE page_id = ?');
        $stmt->execute([$pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return new MarketingPageWikiSettings($pageId, false, null, null);
        }

        $updatedAt = null;
        if (isset($row['updated_at']) && $row['updated_at'] !== null) {
            $updatedAt = new DateTimeImmutable((string) $row['updated_at']);
        }

        return new MarketingPageWikiSettings(
            (int) $row['page_id'],
            (bool) $row['is_active'],
            isset($row['menu_label']) ? (string) $row['menu_label'] : null,
            $updatedAt
        );
    }

    public function updateSettings(int $pageId, bool $isActive, ?string $menuLabel = null): MarketingPageWikiSettings
    {
        $normalizedLabel = $menuLabel !== null ? trim($menuLabel) : null;
        if ($normalizedLabel !== null && $normalizedLabel !== '' && mb_strlen($normalizedLabel) > 64) {
            throw new RuntimeException('Menu label must not exceed 64 characters.');
        }

        $stmt = $this->pdo->prepare('SELECT page_id FROM marketing_page_wiki_settings WHERE page_id = ?');
        $stmt->execute([$pageId]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

        if ($exists) {
            $update = $this->pdo->prepare('UPDATE marketing_page_wiki_settings SET is_active = ?, menu_label = ?, updated_at = CURRENT_TIMESTAMP WHERE page_id = ?');
            $update->execute([$isActive ? 1 : 0, $normalizedLabel, $pageId]);
        } else {
            $insert = $this->pdo->prepare('INSERT INTO marketing_page_wiki_settings (page_id, is_active, menu_label) VALUES (?, ?, ?)');
            $insert->execute([$pageId, $isActive ? 1 : 0, $normalizedLabel]);
        }

        return $this->getSettingsForPage($pageId);
    }
}
