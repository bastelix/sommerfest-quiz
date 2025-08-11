<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Infrastructure\Migrations\Migrator;
use App\Domain\Plan;

/**
 * Service for creating and deleting tenants using separate schemas.
 */
class TenantService
{
    private PDO $pdo;
    private string $migrationsDir;
    private ?NginxService $nginxService;
    /**
     * @var array<string,?string>
     */
    private array $planCache = [];
    /**
     * @var array<string,?array>
     */
    private array $limitCache = [];

    private const RESERVED_SUBDOMAINS = [
        'public',
        'www',
        'so',
        'sa',
        'ss',
        'ns',
        'nsdap',
        'nazi',
        'nazis',
        'hitler',
        'adolf',
        'hj',
        'bdm',
        'kz',
    ];

    public function __construct(PDO $pdo, ?string $migrationsDir = null, ?NginxService $nginxService = null)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__, 2) . '/migrations';
        $this->nginxService = $nginxService;
    }

    /**
     * Create a new tenant schema and run migrations within it.
     */
    public function createTenant(
        string $uid,
        string $schema,
        ?string $plan = null,
        ?string $billing = null,
        ?array $customLimits = null
    ): void {
        if ($this->exists($schema)) {
            throw new \RuntimeException('tenant-exists');
        }
        if ($plan !== null && !Plan::isValid($plan)) {
            throw new \RuntimeException('invalid-plan');
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            Migrator::migrate($this->pdo, $this->migrationsDir);
            $this->seedDemoData();
        } else {
            $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $schema));
            $this->pdo->exec(sprintf('SET search_path TO "%s", public', $schema));
            Migrator::migrate($this->pdo, $this->migrationsDir);
            $this->seedDemoData();
            $this->pdo->exec('SET search_path TO public');
        }
        $start = $plan !== null ? new \DateTimeImmutable() : null;
        $end = $start?->modify('+30 days');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants(' .
            'uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, imprint_street, ' .
            'imprint_zip, imprint_city, imprint_email, custom_limits, plan_started_at, plan_expires_at' .
            ') VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid,
            $schema,
            $plan,
            $billing,
            null,
            null,
            null,
            null,
            null,
            null,
            $customLimits !== null ? json_encode($customLimits) : null,
            $start?->format('Y-m-d H:i:sP'),
            $end?->format('Y-m-d H:i:sP'),
        ]);
        $this->planCache[$schema] = $plan;
        $this->limitCache[$schema] = $customLimits;

        if ($this->nginxService !== null) {
            try {
                $this->nginxService->createVhost($schema);
            } catch (\RuntimeException $e) {
                error_log('Failed to reload nginx: ' . $e->getMessage());
                throw new \RuntimeException('Nginx reload failed – check Docker installation', 0, $e);
            }
        }
    }

    /**
     * Drop the tenant schema and remove its record.
     */
    public function deleteTenant(string $uid): void
    {
        $stmt = $this->pdo->prepare('SELECT subdomain FROM tenants WHERE uid = ?');
        $stmt->execute([$uid]);
        $schema = $stmt->fetchColumn();
        if ($schema === false) {
            return;
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $this->pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schema));
        }
        $del = $this->pdo->prepare('DELETE FROM tenants WHERE uid = ?');
        $del->execute([$uid]);
    }

    /**
     * Check whether a tenant with the given subdomain exists.
     */
    public function exists(string $subdomain): bool
    {
        if ($this->isReserved($subdomain)) {
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $check = $this->pdo->prepare('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?');
            $check->execute([$subdomain]);
            if ($check->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    private function isReserved(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), self::RESERVED_SUBDOMAINS, true);
    }

    private function hasTable(string $name): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() !== false;
        }
        $stmt = $this->pdo->prepare('SELECT to_regclass(?)');
        $stmt->execute([$name]);
        return $stmt->fetchColumn() !== null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            return in_array($column, $cols, true);
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Seed demo data from the bundled data directory into the current schema.
     */
    private function seedDemoData(): void
    {
        $base = dirname(__DIR__, 2);
        $dataDir = $base . '/data';
        if (!is_dir($dataDir)) {
            return;
        }

        $this->pdo->beginTransaction();

        $eventsFile = $dataDir . '/events.json';
        $activeUid = null;
        if ($this->hasTable('events') && $this->hasColumn('events', 'name') && is_readable($eventsFile)) {
            $events = json_decode(file_get_contents($eventsFile), true) ?? [];
            $stmt = $this->pdo->prepare(
                'INSERT INTO events(uid,name,start_date,end_date,description) VALUES(?,?,?,?,?)'
            );
            foreach ($events as $e) {
                $uid = $e['uid'] ?? bin2hex(random_bytes(16));
                if ($activeUid === null) {
                    $activeUid = $uid;
                }
                $stmt->execute([
                    $uid,
                    $e['name'] ?? '',
                    $e['start_date'] ?? date('Y-m-d\TH:i'),
                    $e['end_date'] ?? date('Y-m-d\TH:i'),
                    $e['description'] ?? null,
                ]);
            }
        }

        if ($activeUid !== null) {
            if ($this->hasTable('config')) {
                $cfgFile = $dataDir . '/config.json';
                $cfg = [];
                if (is_readable($cfgFile)) {
                    $cfg = json_decode(file_get_contents($cfgFile), true) ?? [];
                }
                unset($cfg['id']);
                $cfg['event_uid'] = $activeUid;
                $cols = array_keys($cfg);
                if ($cols !== []) {
                    $place = array_map(fn($c) => ':' . $c, $cols);
                    $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . implode(',', $place) . ')';
                    $stmt = $this->pdo->prepare($sql);
                    foreach ($cfg as $k => $v) {
                        if (is_bool($v)) {
                            $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
                        } else {
                            $stmt->bindValue(':' . $k, $v);
                        }
                    }
                    $stmt->execute();
                }
            }
            if ($this->hasTable('active_event')) {
                $this->pdo->prepare('INSERT INTO active_event(event_uid) VALUES(?)')->execute([$activeUid]);
            }
        }

        $catalogDir = $dataDir . '/kataloge';
        $catalogsFile = $catalogDir . '/catalogs.json';
        if ($this->hasTable('catalogs') && $this->hasColumn('catalogs', 'description') && is_readable($catalogsFile)) {
            $catalogs = json_decode(file_get_contents($catalogsFile), true) ?? [];
            $catStmt = $this->pdo->prepare(
                'INSERT INTO catalogs(' .
                    'uid,sort_order,slug,file,name,description,qrcode_url,' .
                    'raetsel_buchstabe,comment,event_uid' .
                ') VALUES(?,?,?,?,?,?,?,?,?,?)'
            );
            $qStmt = $this->hasTable('questions')
                ? $this->pdo->prepare(
                    'INSERT INTO questions(' .
                        'catalog_uid,type,prompt,options,answers,terms,items,sort_order' .
                    ') VALUES(?,?,?,?,?,?,?,?)'
                )
                : null;
            foreach ($catalogs as $cat) {
                $catStmt->execute([
                    $cat['uid'] ?? '',
                    $cat['id'] ?? 0,
                    $cat['slug'] ?? '',
                    $cat['file'] ?? '',
                    $cat['name'] ?? '',
                    $cat['description'] ?? null,
                    $cat['qrcode_url'] ?? null,
                    $cat['raetsel_buchstabe'] ?? null,
                    $cat['comment'] ?? null,
                    $activeUid,
                ]);
                if ($qStmt !== null) {
                    $file = $catalogDir . '/' . ($cat['file'] ?? '');
                    if (is_readable($file)) {
                        $questions = json_decode(file_get_contents($file), true) ?? [];
                        foreach ($questions as $i => $q) {
                            $qStmt->execute([
                                $cat['uid'] ?? '',
                                $q['type'] ?? '',
                                $q['prompt'] ?? '',
                                isset($q['options']) ? json_encode($q['options']) : null,
                                isset($q['answers']) ? json_encode($q['answers']) : null,
                                isset($q['terms']) ? json_encode($q['terms']) : null,
                                isset($q['items']) ? json_encode($q['items']) : null,
                                $i + 1,
                            ]);
                        }
                    }
                }
            }
        }

        $this->pdo->commit();
    }

    /**
     * Retrieve the plan for a tenant identified by subdomain.
     */
    public function getPlanBySubdomain(string $subdomain): ?string
    {
        if (array_key_exists($subdomain, $this->planCache)) {
            return $this->planCache[$subdomain];
        }
        $stmt = $this->pdo->prepare('SELECT plan FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        $plan = $stmt->fetchColumn();
        $this->planCache[$subdomain] = $plan === false ? null : (string) $plan;
        return $this->planCache[$subdomain];
    }

    /**
     * Retrieve custom limits for a tenant identified by subdomain.
     *
     * @return array<string,int|null>|null
     */
    public function getCustomLimitsBySubdomain(string $subdomain): ?array
    {
        if (array_key_exists($subdomain, $this->limitCache)) {
            return $this->limitCache[$subdomain];
        }
        $stmt = $this->pdo->prepare('SELECT custom_limits FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        $json = $stmt->fetchColumn();
        if ($json === false || $json === null) {
            $this->limitCache[$subdomain] = null;
            return null;
        }
        $data = json_decode((string) $json, true);
        $this->limitCache[$subdomain] = is_array($data) ? $data : null;
        return $this->limitCache[$subdomain];
    }

    /**
     * Set custom limits for a tenant.
     *
     * @param array<string,int|null>|null $limits
     */
    public function setCustomLimits(string $subdomain, ?array $limits): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET custom_limits = ? WHERE subdomain = ?');
        $stmt->execute([$limits !== null ? json_encode($limits) : null, $subdomain]);
        $this->limitCache[$subdomain] = $limits;
    }

    /**
     * Retrieve effective limits for a tenant.
     *
     * @return array<string,int|null>
     */
    public function getLimitsBySubdomain(string $subdomain): array
    {
        $custom = $this->getCustomLimitsBySubdomain($subdomain);
        if ($custom !== null) {
            return $custom;
        }
        $plan = $this->getPlanBySubdomain($subdomain);
        return $plan !== null ? Plan::limits($plan) : [];
    }

    /**
     * Retrieve a tenant by its subdomain.
     *
     * @return array{
     *   uid:string,
     *   subdomain:string,
     *   plan:?string,
     *   billing_info:?string,
     *   stripe_customer_id:?string,
     *   imprint_name:?string,
     *   imprint_street:?string,
     *   imprint_zip:?string,
     *   imprint_city:?string,
     *   imprint_email:?string,
     *   plan_started_at:?string,
     *   plan_expires_at:?string,
     *   created_at:string
     * }|null
     */
    public function getBySubdomain(string $subdomain): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, imprint_street, imprint_zip, ' .
            'imprint_city, imprint_email, custom_limits, plan_started_at, plan_expires_at, created_at FROM tenants WHERE subdomain = ?'
        );
        $stmt->execute([$subdomain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if ($row['custom_limits'] !== null) {
            $row['custom_limits'] = json_decode((string) $row['custom_limits'], true);
        }
        return $row;
    }

    /**
     * Update profile information for a tenant identified by subdomain.
     *
     * @param array<string,mixed> $data
     */
    public function updateProfile(string $subdomain, array $data): void
    {
        $fields = [
            'plan',
            'billing_info',
            'stripe_customer_id',
            'imprint_name',
            'imprint_street',
            'imprint_zip',
            'imprint_city',
            'imprint_email',
            'custom_limits',
        ];
        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                if ($f === 'plan' && $data[$f] !== null && !Plan::isValid((string) $data[$f])) {
                    throw new \RuntimeException('invalid-plan');
                }
                $set[] = $f . ' = ?';
                $params[] = $f === 'custom_limits'
                    ? ($data[$f] !== null ? json_encode($data[$f]) : null)
                    : $data[$f];
            }
        }

        $planChange = array_key_exists('plan', $data) || array_key_exists('plan_started_at', $data);
        if ($planChange) {
            if (array_key_exists('plan', $data) && $data['plan'] === null) {
                $set[] = 'plan_started_at = ?';
                $params[] = null;
                $set[] = 'plan_expires_at = ?';
                $params[] = null;
            } else {
                $start = array_key_exists('plan_started_at', $data)
                    ? new \DateTimeImmutable((string) $data['plan_started_at'])
                    : new \DateTimeImmutable();
                $set[] = 'plan_started_at = ?';
                $params[] = $start->format('Y-m-d H:i:sP');
                $set[] = 'plan_expires_at = ?';
                $params[] = $start->modify('+30 days')->format('Y-m-d H:i:sP');
            }
        }

        if ($set === []) {
            return;
        }
        $params[] = $subdomain;
        $sql = 'UPDATE tenants SET ' . implode(', ', $set) . ' WHERE subdomain = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if (array_key_exists('plan', $data)) {
            $this->planCache[$subdomain] = $data['plan'];
        }
        if (array_key_exists('custom_limits', $data)) {
            $this->limitCache[$subdomain] = $data['custom_limits'];
        }
    }

    /**
     * Update a tenant identified by its Stripe customer id.
     *
     * @param array<string,mixed> $data
     */
    public function updateByStripeCustomerId(string $customerId, array $data): void
    {
        $stmt = $this->pdo->prepare('SELECT subdomain FROM tenants WHERE stripe_customer_id = ?');
        $stmt->execute([$customerId]);
        $sub = $stmt->fetchColumn();
        if ($sub === false) {
            return;
        }
        $this->updateProfile((string) $sub, $data);
    }

    /**
     * Retrieve all tenants ordered by creation date.
     *
     * @return list<array{
     *   uid:string,
     *   subdomain:string,
     *   plan:?string,
     *   billing_info:?string,
     *   stripe_customer_id:?string,
     *   imprint_name:?string,
     *   imprint_street:?string,
     *   imprint_zip:?string,
     *   imprint_city:?string,
     *   imprint_email:?string,
     *   plan_started_at:?string,
     *   plan_expires_at:?string,
     *   created_at:string
     * }>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, imprint_street, imprint_zip, ' .
            'imprint_city, imprint_email, custom_limits, plan_started_at, plan_expires_at, created_at FROM tenants ORDER BY created_at'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['custom_limits'] !== null) {
                $row['custom_limits'] = json_decode((string) $row['custom_limits'], true);
            }
        }
        return $rows;
    }
}
