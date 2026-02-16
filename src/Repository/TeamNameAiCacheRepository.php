<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

use function array_fill;
use function array_merge;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Persisted cache for AI generated team names keyed by event and filter set.
 */
final class TeamNameAiCacheRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, array{names: list<string>, metadata: array{domains: list<string>, tones: list<string>, locale: string}}>
     */
    public function loadForEvent(string $eventId): array
    {
        if ($eventId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT cache_key, name, filters FROM team_name_ai_cache WHERE event_id = ? ORDER BY id'
        );
        $stmt->execute([$eventId]);

        $entries = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $cacheKey = (string) ($row['cache_key'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            if ($cacheKey === '' || $name === '') {
                continue;
            }

            $metadata = $this->decodeFilters($row['filters'] ?? null);

            if (!isset($entries[$cacheKey])) {
                $entries[$cacheKey] = [
                    'names' => [],
                    'metadata' => $metadata,
                ];
            }

            $entries[$cacheKey]['metadata'] = $metadata;

            if (!in_array($name, $entries[$cacheKey]['names'], true)) {
                $entries[$cacheKey]['names'][] = $name;
            }
        }

        $stmt->closeCursor();

        return $entries;
    }

    /**
     * @param list<string> $names
     * @param array{domains?: array<int, string>, tones?: array<int, string>, locale?: string} $filters
     */
    public function persistNames(string $eventId, string $cacheKey, array $names, array $filters = [], ?string $namespace = null): void
    {
        $unique = [];
        foreach ($names as $name) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            $unique[$candidate] = $candidate;
        }

        if ($eventId === '' || $cacheKey === '' || $unique === []) {
            return;
        }

        $payload = $this->encodeFilters($filters);
        $resolvedNamespace = $namespace !== null && $namespace !== '' ? $namespace : 'default';

        $sql = 'INSERT INTO team_name_ai_cache (event_id, cache_key, name, filters, namespace) VALUES (?,?,?,?,?) '
            . 'ON CONFLICT (event_id, cache_key, name) DO UPDATE '
            . 'SET filters = EXCLUDED.filters, namespace = EXCLUDED.namespace, updated_at = CURRENT_TIMESTAMP';

        $useTransaction = count($unique) > 1;

        if ($useTransaction) {
            $this->pdo->beginTransaction();
        }

        $stmt = $this->pdo->prepare($sql);

        try {
            foreach (array_values($unique) as $name) {
                $stmt->execute([$eventId, $cacheKey, $name, $payload, $resolvedNamespace]);
            }

            if ($useTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        } finally {
            $stmt->closeCursor();
        }
    }

    /**
     * @param list<string> $names
     */
    public function deleteNames(string $eventId, string $cacheKey, array $names): void
    {
        $unique = [];
        foreach ($names as $name) {
            $candidate = trim((string) $name);
            if ($candidate === '') {
                continue;
            }
            $unique[$candidate] = $candidate;
        }

        if ($eventId === '' || $cacheKey === '' || $unique === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $params = array_merge([$eventId, $cacheKey], array_values($unique));

        $stmt = $this->pdo->prepare(
            'DELETE FROM team_name_ai_cache WHERE event_id = ? AND cache_key = ? AND name IN (' . $placeholders . ')'
        );
        $stmt->execute($params);
        $stmt->closeCursor();
    }

    public function deleteEvent(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM team_name_ai_cache WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $stmt->closeCursor();
    }

    /**
     * @return array{domains: list<string>, tones: list<string>, locale: string}
     */
    private function decodeFilters(mixed $payload): array
    {
        $data = null;

        if (is_string($payload) && $payload !== '') {
            try {
                $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $data = null;
            }
        } elseif (is_array($payload)) {
            $data = $payload;
        }

        $domains = $this->filterStringList($data['domains'] ?? []);
        $tones = $this->filterStringList($data['tones'] ?? []);
        $locale = trim((string) ($data['locale'] ?? ''));

        return [
            'domains' => $domains,
            'tones' => $tones,
            'locale' => $locale,
        ];
    }

    /**
     * @param array{domains?: array<int, string>, tones?: array<int, string>, locale?: string} $filters
     */
    private function encodeFilters(array $filters): string
    {
        $payload = [
            'domains' => $this->filterStringList($filters['domains'] ?? []),
            'tones' => $this->filterStringList($filters['tones'] ?? []),
            'locale' => trim((string) ($filters['locale'] ?? '')),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private function filterStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }
            if (!in_array($candidate, $result, true)) {
                $result[] = $candidate;
            }
        }

        return $result;
    }
}
