<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\CmsFooterBlock;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class CmsFooterBlockService
{
    private const ALLOWED_TYPES = ['menu', 'text', 'social', 'contact', 'newsletter', 'html'];
    private const ALLOWED_SLOTS = ['footer_1', 'footer_2', 'footer_3'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * Get all footer blocks for a specific namespace, slot, and locale
     *
     * @return CmsFooterBlock[]
     */
    public function getBlocksForSlot(
        string $namespace,
        string $slot,
        ?string $locale = null,
        bool $onlyActive = true
    ): array {
        $sql = 'SELECT id, namespace, slot, type, content, position, locale, is_active, updated_at
                FROM marketing_footer_blocks
                WHERE namespace = :namespace AND slot = :slot';

        $params = ['namespace' => $namespace, 'slot' => $slot];

        if ($locale !== null) {
            $sql .= ' AND locale = :locale';
            $params['locale'] = $locale;
        }

        if ($onlyActive) {
            $sql .= ' AND is_active = TRUE';
        }

        $sql .= ' ORDER BY position ASC, id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $blocks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $blocks[] = $this->hydrateBlock($row);
        }

        return $blocks;
    }

    /**
     * Get a single footer block by ID
     */
    public function getBlockById(int $id): ?CmsFooterBlock
    {
        $sql = 'SELECT id, namespace, slot, type, content, position, locale, is_active, updated_at
                FROM marketing_footer_blocks
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrateBlock($row);
    }

    /**
     * Create a new footer block
     *
     * @param array<string, mixed> $content
     */
    public function createBlock(
        string $namespace,
        string $slot,
        string $type,
        array $content,
        int $position,
        string $locale,
        bool $isActive
    ): CmsFooterBlock {
        $this->validateType($type);
        $this->validateSlot($slot);

        $sql = 'INSERT INTO marketing_footer_blocks
                (namespace, slot, type, content, position, locale, is_active)
                VALUES (:namespace, :slot, :type, :content, :position, :locale, :is_active)
                RETURNING id, namespace, slot, type, content, position, locale, is_active, updated_at';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'namespace' => $namespace,
            'slot' => $slot,
            'type' => $type,
            'content' => json_encode($content),
            'position' => $position,
            'locale' => $locale,
            'is_active' => $isActive ? 'true' : 'false',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Failed to create footer block');
        }

        return $this->hydrateBlock($row);
    }

    /**
     * Update an existing footer block
     *
     * @param array<string, mixed> $content
     */
    public function updateBlock(
        int $id,
        string $type,
        array $content,
        int $position,
        bool $isActive,
        ?string $slot = null
    ): CmsFooterBlock {
        $this->validateType($type);

        $setClauses = 'type = :type, content = :content, position = :position, is_active = :is_active';
        $params = [
            'id' => $id,
            'type' => $type,
            'content' => json_encode($content),
            'position' => $position,
            'is_active' => $isActive ? 'true' : 'false',
        ];

        if ($slot !== null) {
            $this->validateSlot($slot);
            $setClauses .= ', slot = :slot';
            $params['slot'] = $slot;
        }

        $sql = "UPDATE marketing_footer_blocks
                SET {$setClauses}
                WHERE id = :id
                RETURNING id, namespace, slot, type, content, position, locale, is_active, updated_at";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Footer block not found or update failed');
        }

        return $this->hydrateBlock($row);
    }

    /**
     * Delete a footer block
     */
    public function deleteBlock(int $id): void
    {
        $sql = 'DELETE FROM marketing_footer_blocks WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Footer block not found');
        }
    }

    /**
     * Reorder footer blocks within a slot
     *
     * @param array<int> $orderedIds
     */
    public function reorderBlocks(string $namespace, string $slot, string $locale, array $orderedIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $sql = 'UPDATE marketing_footer_blocks
                    SET position = :position
                    WHERE id = :id AND namespace = :namespace AND slot = :slot AND locale = :locale';

            $stmt = $this->pdo->prepare($sql);

            foreach ($orderedIds as $position => $id) {
                $stmt->execute([
                    'id' => $id,
                    'position' => $position,
                    'namespace' => $namespace,
                    'slot' => $slot,
                    'locale' => $locale,
                ]);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Failed to reorder footer blocks: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateBlock(array $row): CmsFooterBlock
    {
        $content = json_decode((string) $row['content'], true);
        if (!is_array($content)) {
            $content = [];
        }

        $updatedAt = null;
        if (isset($row['updated_at']) && is_string($row['updated_at'])) {
            $updatedAt = new DateTimeImmutable($row['updated_at']);
        }

        return new CmsFooterBlock(
            (int) $row['id'],
            (string) $row['namespace'],
            (string) $row['slot'],
            (string) $row['type'],
            $content,
            (int) $row['position'],
            (string) $row['locale'],
            (bool) $row['is_active'],
            $updatedAt
        );
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new RuntimeException(
                'Invalid block type. Allowed types: ' . implode(', ', self::ALLOWED_TYPES)
            );
        }
    }

    private function validateSlot(string $slot): void
    {
        if (!in_array($slot, self::ALLOWED_SLOTS, true)) {
            throw new RuntimeException(
                'Invalid slot. Allowed slots: ' . implode(', ', self::ALLOWED_SLOTS)
            );
        }
    }
}
