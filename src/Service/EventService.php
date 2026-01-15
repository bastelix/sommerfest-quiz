<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use App\Service\ConfigService;
use App\Service\TenantService;
use App\Service\PageService;
use App\Service\NamespaceValidator;

/**
 * Service for managing quiz events.
 */
class EventService
{
    private PDO $pdo;
    private ConfigService $config;
    private ?TenantService $tenants;
    private string $subdomain;
    private NamespaceValidator $namespaceValidator;

    public function __construct(
        PDO $pdo,
        ?ConfigService $config = null,
        ?TenantService $tenants = null,
        string $subdomain = ''
    ) {
        $this->pdo = $pdo;
        $this->config = $config ?? new ConfigService($pdo);
        $this->tenants = $tenants;
        $this->subdomain = $subdomain;
        $this->namespaceValidator = new NamespaceValidator();
    }

    /**
     * Retrieve all events ordered by sort order.
     *
     * @param string|null $namespace
     * @return list<array{
     *     uid:string,
     *     slug:string,
     *     namespace:string,
     *     name:string,
     *     start_date:?string,
     *     end_date:?string,
     *     description:?string,
     *     published:bool
     * }>
     */
    public function getAll(?string $namespace = null): array {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $primarySql = 'SELECT uid,slug,name,start_date,end_date,description,published,sort_order,namespace '
            . 'FROM events WHERE namespace = ? ORDER BY sort_order';
        $legacySql = 'SELECT uid,slug,name,start_date,end_date,description,published,namespace '
            . 'FROM events WHERE namespace = ? ORDER BY name';
        $rows = [];

        try {
            $stmt = $this->pdo->prepare($primarySql);
            $stmt->execute([$resolvedNamespace]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Failed to load events from database: ' . $exception->getMessage());

            try {
                $stmt = $this->pdo->prepare($legacySql);
                $stmt->execute([$resolvedNamespace]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $legacyException) {
                error_log('Legacy event query failed: ' . $legacyException->getMessage());
            }
        }

        if ($rows === []) {
            $rows = $this->loadEventsFromJson($resolvedNamespace);
        }

        $events = array_map(function (array $row) use ($resolvedNamespace) {
            $uid = array_key_exists('uid', $row) ? $row['uid'] : ($row['id'] ?? '');
            $row['uid'] = (string) $uid;
            $row['slug'] = (string) (array_key_exists('slug', $row) ? $row['slug'] : $row['uid']);
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? $resolvedNamespace);
            $row['start_date'] = $this->formatDate($row['start_date'] ?? null);
            $row['end_date'] = $this->formatDate($row['end_date'] ?? null);
            $row['published'] = (bool)($row['published'] ?? false);
            return $row;
        }, $rows);

        error_log('Event count: ' . count($events));

        return $events;
    }

    /**
     * Load the fallback event list from the static JSON file.
     *
     * @return list<array<string, mixed>>
     */
    private function loadEventsFromJson(?string $namespace = null): array {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $path = dirname(__DIR__, 2) . '/data/events.json';
        if (!is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $json = json_decode($contents, true);
        if (!is_array($json)) {
            return [];
        }

        $events = [];
        foreach ($json as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventNamespace = $this->normalizeNamespace($event['namespace'] ?? null);
            if ($eventNamespace !== $resolvedNamespace) {
                continue;
            }
            $events[] = $event;
        }

        return $events;
    }

    /**
     * Replace all events with the provided list.
     *
     * @param list<array{
     *     uid?:string,
     *     slug?:string,
     *     namespace?:string,
     *     name:string,
     *     start_date?:string,
     *     end_date?:string,
     *     description?:string,
     *     published?:bool,
     *     draft?:bool
     * }> $events
     * @param string|null $namespace
     */
    public function saveAll(array $events, ?string $namespace = null): void {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $existingStmt = $this->pdo->prepare('SELECT uid FROM events WHERE namespace = ?');
        $existingStmt->execute([$resolvedNamespace]);
        $existing = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($this->tenants !== null && $this->subdomain !== '') {
            $limits = $this->tenants->getLimitsBySubdomain($this->subdomain);
            $max = $limits['maxEvents'] ?? null;
            if ($max !== null && count($events) > $max) {
                throw new \RuntimeException('max-events-exceeded');
            }
        }

        $this->pdo->beginTransaction();

        $updateStmt = $this->pdo->prepare(
            'UPDATE events SET name = ?, start_date = ?, end_date = ?, ' .
            'description = ?, published = ?, sort_order = ?, slug = ?, namespace = ? WHERE uid = ? AND namespace = ?'
        );
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO events(uid,slug,name,start_date,end_date,description,published,sort_order,namespace) ' .
            'VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $uids = [];
        $processed = 0;

        foreach ($events as $idx => $event) {
            $uid = $event['uid'] ?? bin2hex(random_bytes(16));
            $rawName = (string) $event['name'];
            $name = trim($rawName);
            $isDraft = !empty($event['draft'])
                || str_starts_with($rawName, '__draft__')
                || str_starts_with($name, '__draft__');
            if ($name === '' || $isDraft) {
                continue;
            }
            $uids[] = $uid;
            $processed++;
            $start = $event['start_date'] ?? '';
            if ($start === '') {
                $start = date('Y-m-d\TH:i');
            }
            $end = $event['end_date'] ?? '';
            if ($end === '') {
                $end = date('Y-m-d\TH:i');
            }
            $desc = $event['description'] ?? null;
            $published = filter_var($event['published'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $sort = $idx;
            $slug = (string) ($event['slug'] ?? $uid);
            $namespace = $resolvedNamespace;

            if (in_array($uid, $existing, true)) {
                $updateStmt->execute([$name, $start, $end, $desc, $published, $sort, $slug, $namespace, $uid, $namespace]);
            } else {
                $insertStmt->execute([$uid, $slug, $name, $start, $end, $desc, $published, $sort, $namespace]);
                $this->config->ensureConfigForEvent($uid);
            }
        }

        if ($processed > 0 && $uids) {
            $placeholders = implode(',', array_fill(0, count($uids), '?'));
            $delStmt = $this->pdo->prepare("DELETE FROM events WHERE namespace = ? AND uid NOT IN ($placeholders)");
            $delStmt->execute(array_merge([$resolvedNamespace], $uids));
        } elseif (empty($events)) {
            $delStmt = $this->pdo->prepare('DELETE FROM events WHERE namespace = ?');
            $delStmt->execute([$resolvedNamespace]);
        }

        $this->pdo->commit();

        $countStmt = $this->pdo->prepare('SELECT uid FROM events WHERE namespace = ? LIMIT 2');
        $countStmt->execute([$resolvedNamespace]);
        $eventUids = $countStmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($eventUids) === 1) {
            $this->config->setActiveEventUid((string) $eventUids[0]);
        }
    }

    /**
     * Return the first event or null if none exist.
     *
     * @param string|null $namespace
     * @return array{uid:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getFirst(?string $namespace = null): ?array {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $stmt = $this->pdo->prepare(
            'SELECT uid,name,start_date,end_date,description,namespace FROM events WHERE namespace = ? ORDER BY name LIMIT 1'
        );
        $stmt->execute([$resolvedNamespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? $resolvedNamespace);
        $row['start_date'] = $this->formatDate($row['start_date']);
        $row['end_date'] = $this->formatDate($row['end_date']);
        return $row;
    }

    /**
     * Retrieve a specific event by its UID.
     *
     * @param string|null $namespace
     * @return array{uid:string,slug:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getByUid(string $uid, ?string $namespace = null): ?array {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $stmt = $this->pdo->prepare(
            'SELECT uid,slug,name,start_date,end_date,description,namespace FROM events WHERE uid = ? AND namespace = ?'
        );
        $stmt->execute([$uid, $resolvedNamespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? $resolvedNamespace);
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            return $row;
        }

        $fallbackEvents = $this->loadEventsFromJson($resolvedNamespace);
        foreach ($fallbackEvents as $event) {
            if ((string)($event['uid'] ?? '') === $uid) {
                return [
                    'uid' => (string) $event['uid'],
                    'slug' => (string) ($event['slug'] ?? $event['uid']),
                    'name' => (string) $event['name'],
                    'start_date' => $event['start_date'] ?? null,
                    'end_date' => $event['end_date'] ?? null,
                    'description' => $event['description'] ?? null,
                    'namespace' => $this->normalizeNamespace($event['namespace'] ?? $resolvedNamespace),
                ];
            }
        }

        return null;
    }

    /**
     * Retrieve a specific event by its slug.
     *
     * @param string|null $namespace
     * @return array{uid:string,slug:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getBySlug(string $slug, ?string $namespace = null): ?array {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $stmt = $this->pdo->prepare(
            'SELECT uid,slug,name,start_date,end_date,description,namespace FROM events WHERE slug = ? AND namespace = ?'
        );
        $stmt->execute([$slug, $resolvedNamespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? $resolvedNamespace);
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            return $row;
        }
        return null;
    }

    /**
     * Find the UID for the given event slug.
     *
     * @param string|null $namespace
     */
    public function uidBySlug(string $slug, ?string $namespace = null): ?string {
        $resolvedNamespace = $this->resolveNamespace($namespace);
        $stmt = $this->pdo->prepare('SELECT uid FROM events WHERE slug = ? AND namespace = ?');
        $stmt->execute([$slug, $resolvedNamespace]);
        $uid = $stmt->fetchColumn();
        return $uid === false ? null : (string) $uid;
    }

    private function formatDate(?string $value): ?string {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function normalizeNamespace(mixed $candidate): string {
        $normalized = $this->namespaceValidator->normalizeCandidate($candidate);
        return $normalized ?? PageService::DEFAULT_NAMESPACE;
    }

    private function resolveNamespace(?string $namespace): string
    {
        return $this->normalizeNamespace($namespace);
    }
}
