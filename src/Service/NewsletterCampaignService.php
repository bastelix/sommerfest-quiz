<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\NewsletterCampaign;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Manages newsletter campaign persistence.
 */
class NewsletterCampaignService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @return NewsletterCampaign[]
     */
    public function getAll(string $namespace): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM newsletter_campaigns WHERE namespace = :namespace ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['namespace' => $this->normalizeNamespace($namespace)]);

        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function find(int $id): ?NewsletterCampaign
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param int[] $newsIds
     */
    public function create(
        string $namespace,
        string $name,
        array $newsIds,
        ?string $templateId,
        ?string $audienceId,
        string $status,
        ?DateTimeImmutable $scheduledFor
    ): NewsletterCampaign {
        $normalizedName = $this->normalizeName($name);
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $normalizedNewsIds = $this->normalizeNewsIds($newsIds);
        $normalizedStatus = $this->normalizeStatus($status);

        $stmt = $this->pdo->prepare(
            'INSERT INTO newsletter_campaigns '
            . '(namespace, name, news_ids, template_id, audience_id, status, scheduled_for, created_at, updated_at) '
            . 'VALUES (:namespace, :name, :news_ids, :template_id, :audience_id, :status, :scheduled_for, '
            . 'CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            'namespace' => $normalizedNamespace,
            'name' => $normalizedName,
            'news_ids' => json_encode($normalizedNewsIds, JSON_UNESCAPED_SLASHES),
            'template_id' => $this->normalizeNullable($templateId),
            'audience_id' => $this->normalizeNullable($audienceId),
            'status' => $normalizedStatus,
            'scheduled_for' => $this->formatTimestamp($scheduledFor),
        ]);

        $campaign = $this->find((int) $this->pdo->lastInsertId());
        if ($campaign === null) {
            throw new RuntimeException('Failed to persist newsletter campaign.');
        }

        return $campaign;
    }

    /**
     * @param int[] $newsIds
     */
    public function update(
        int $id,
        string $namespace,
        string $name,
        array $newsIds,
        ?string $templateId,
        ?string $audienceId,
        string $status,
        ?DateTimeImmutable $scheduledFor
    ): NewsletterCampaign {
        $campaign = $this->find($id);
        if ($campaign === null) {
            throw new RuntimeException('Campaign not found.');
        }

        $normalizedName = $this->normalizeName($name);
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $normalizedNewsIds = $this->normalizeNewsIds($newsIds);
        $normalizedStatus = $this->normalizeStatus($status);

        $stmt = $this->pdo->prepare(
            'UPDATE newsletter_campaigns SET namespace = :namespace, name = :name, news_ids = :news_ids, '
            . 'template_id = :template_id, audience_id = :audience_id, status = :status, scheduled_for = :scheduled_for, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );

        $stmt->execute([
            'namespace' => $normalizedNamespace,
            'name' => $normalizedName,
            'news_ids' => json_encode($normalizedNewsIds, JSON_UNESCAPED_SLASHES),
            'template_id' => $this->normalizeNullable($templateId),
            'audience_id' => $this->normalizeNullable($audienceId),
            'status' => $normalizedStatus,
            'scheduled_for' => $this->formatTimestamp($scheduledFor),
            'id' => $id,
        ]);

        $updated = $this->find($id);
        if ($updated === null) {
            throw new RuntimeException('Failed to update newsletter campaign.');
        }

        return $updated;
    }

    public function markSent(int $id, ?string $providerCampaignId, ?string $providerMessageId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE newsletter_campaigns SET status = :status, provider_campaign_id = :campaign_id, '
            . 'provider_message_id = :message_id, sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'sent',
            'campaign_id' => $this->normalizeNullable($providerCampaignId),
            'message_id' => $this->normalizeNullable($providerMessageId),
            'id' => $id,
        ]);
    }

    public function markFailed(int $id, string $status = 'failed'): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE newsletter_campaigns SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'status' => $this->normalizeStatus($status),
            'id' => $id,
        ]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): NewsletterCampaign
    {
        $newsIds = $this->decodeNewsIds($row['news_ids'] ?? '[]');

        return new NewsletterCampaign(
            (int) $row['id'],
            $this->normalizeNamespace($row['namespace'] ?? null),
            (string) ($row['name'] ?? ''),
            $newsIds,
            $this->normalizeNullable($row['template_id'] ?? null),
            $this->normalizeNullable($row['audience_id'] ?? null),
            $this->normalizeStatus((string) ($row['status'] ?? 'draft')),
            $this->normalizeNullable($row['provider_campaign_id'] ?? null),
            $this->normalizeNullable($row['provider_message_id'] ?? null),
            $this->parseTimestamp($row['scheduled_for'] ?? null),
            $this->parseTimestamp($row['sent_at'] ?? null),
            new DateTimeImmutable((string) ($row['created_at'] ?? 'now')),
            new DateTimeImmutable((string) ($row['updated_at'] ?? 'now')),
        );
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function normalizeNewsIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $int = (int) $id;
            if ($int > 0) {
                $normalized[$int] = $int;
            }
        }

        return array_values($normalized);
    }

    private function normalizeName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw new RuntimeException('Campaign name is required.');
        }

        return $normalized;
    }

    private function normalizeNamespace(?string $namespace): string
    {
        $normalized = is_string($namespace) ? strtolower(trim($namespace)) : '';

        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return 'draft';
        }

        return $normalized;
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseTimestamp(?string $timestamp): ?DateTimeImmutable
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable(trim($timestamp));
        } catch (\Exception) {
            return null;
        }
    }

    private function formatTimestamp(?DateTimeImmutable $timestamp): ?string
    {
        return $timestamp?->format(DATE_ATOM);
    }

    /**
     * @return int[]
     */
    private function decodeNewsIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } else {
            $decoded = $value;
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeNewsIds($decoded);
    }
}
