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
     * Retrieve all events ordered by name.
     *
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
    public function getAll(): array {
        $primarySql = 'SELECT uid,slug,name,start_date,end_date,description,published,sort_order,namespace FROM events ORDER BY sort_order';
        $legacySql = 'SELECT uid,slug,name,start_date,end_date,description,published FROM events ORDER BY name';
        $rows = [];

        try {
            $stmt = $this->pdo->query($primarySql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Failed to load events from database: ' . $exception->getMessage());

            try {
                $stmt = $this->pdo->query($legacySql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $legacyException) {
                error_log('Legacy event query failed: ' . $legacyException->getMessage());
            }
        }

        if ($rows === []) {
            $rows = $this->loadEventsFromJson();
        }

        $events = array_map(function (array $row) {
            $uid = array_key_exists('uid', $row) ? $row['uid'] : ($row['id'] ?? '');
            $row['uid'] = (string) $uid;
            $row['slug'] = (string) (array_key_exists('slug', $row) ? $row['slug'] : $row['uid']);
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? null);
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
    private function loadEventsFromJson(): array {
        $path = dirname(__DIR__, 2) . '/data/events.json';
        if (!is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $json = json_decode($contents, true);
        return is_array($json) ? $json : [];
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
     */
    public function saveAll(array $events): void {
        $existingStmt = $this->pdo->query('SELECT uid FROM events');
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
            'description = ?, published = ?, sort_order = ?, slug = ?, namespace = ? WHERE uid = ?'
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
            $namespace = $this->normalizeNamespace($event['namespace'] ?? null);

            if (in_array($uid, $existing, true)) {
                $updateStmt->execute([$name, $start, $end, $desc, $published, $sort, $slug, $namespace, $uid]);
            } else {
                $insertStmt->execute([$uid, $slug, $name, $start, $end, $desc, $published, $sort, $namespace]);
                $this->config->ensureConfigForEvent($uid);
            }
        }

        if ($processed > 0 && $uids) {
            $placeholders = implode(',', array_fill(0, count($uids), '?'));
            $delStmt = $this->pdo->prepare("DELETE FROM events WHERE uid NOT IN ($placeholders)");
            $delStmt->execute($uids);
        } elseif (empty($events)) {
            $this->pdo->exec('DELETE FROM events');
        }

        $this->pdo->commit();

        $countStmt = $this->pdo->query('SELECT uid FROM events LIMIT 2');
        $eventUids = $countStmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($eventUids) === 1) {
            $this->config->setActiveEventUid((string) $eventUids[0]);
        }
    }

    /**
     * Return the first event or null if none exist.
     *
     * @return array{uid:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getFirst(): ?array {
        $stmt = $this->pdo->query('SELECT uid,name,start_date,end_date,description,namespace FROM events ORDER BY name LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? null);
        $row['start_date'] = $this->formatDate($row['start_date']);
        $row['end_date'] = $this->formatDate($row['end_date']);
        return $row;
    }

    /**
     * Retrieve a specific event by its UID.
     *
     * @return array{uid:string,slug:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getByUid(string $uid): ?array {
        $stmt = $this->pdo->prepare('SELECT uid,slug,name,start_date,end_date,description,namespace FROM events WHERE uid = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? null);
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            return $row;
        }

        $path = dirname(__DIR__, 2) . '/data/events.json';
        if (is_readable($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) {
                foreach ($json as $event) {
                    if ((string)($event['uid'] ?? '') === $uid) {
                        return [
                            'uid' => (string) $event['uid'],
                            'slug' => (string) ($event['slug'] ?? $event['uid']),
                            'name' => (string) $event['name'],
                            'start_date' => $event['start_date'] ?? null,
                            'end_date' => $event['end_date'] ?? null,
                            'description' => $event['description'] ?? null,
                            'namespace' => $this->normalizeNamespace($event['namespace'] ?? null),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retrieve a specific event by its slug.
     *
     * @return array{uid:string,slug:string,name:string,start_date:?string,end_date:?string,description:?string,namespace:string}|null
     */
    public function getBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare('SELECT uid,slug,name,start_date,end_date,description,namespace FROM events WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['namespace'] = $this->normalizeNamespace($row['namespace'] ?? null);
            $row['start_date'] = $this->formatDate($row['start_date']);
            $row['end_date'] = $this->formatDate($row['end_date']);
            return $row;
        }
        return null;
    }

    /**
     * Find the UID for the given event slug.
     */
    public function uidBySlug(string $slug): ?string {
        $stmt = $this->pdo->prepare('SELECT uid FROM events WHERE slug = ?');
        $stmt->execute([$slug]);
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
}
