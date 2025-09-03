<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use App\Infrastructure\Database;
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

    public function __construct(?PDO $pdo = null, ?string $migrationsDir = null, ?NginxService $nginxService = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
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
        ?string $email = null,
        ?string $imprintName = null,
        ?string $imprintStreet = null,
        ?string $imprintZip = null,
        ?string $imprintCity = null,
        ?array $customLimits = null
    ): void {
        if ($this->exists($schema)) {
            throw new \RuntimeException('tenant-exists');
        }
        if ($plan !== null && Plan::tryFrom($plan) === null) {
            throw new \RuntimeException('invalid-plan');
        }

        try {
            $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $schema));
        } catch (PDOException $e) {
            if ($e->getCode() === '42P06') {
                throw new \RuntimeException('schema-exists', 0, $e);
            }
            throw new \RuntimeException('schema-create-failed: ' . $e->getMessage(), 0, $e);
        }

        try {
            $this->pdo->exec(sprintf('SET search_path TO "%s", public', $schema));
            Migrator::migrate($this->pdo, $this->migrationsDir);
            $this->seedDemoData();
        } catch (PDOException $e) {
            throw new \RuntimeException('migration-failed: ' . $e->getMessage(), 0, $e);
        } finally {
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
            $imprintName,
            $imprintStreet,
            $imprintZip,
            $imprintCity,
            $email,
            $customLimits !== null ? json_encode($customLimits) : null,
            $start?->format('Y-m-d H:i:sP'),
            $end?->format('Y-m-d H:i:sP'),
        ]);

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
        $this->pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schema));
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

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = ? LIMIT 1"
            );
            $stmt->execute([$subdomain]);

            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        } catch (PDOException $e) {
            // ignore missing information_schema tables
        }

        return false;
    }

    private function isReserved(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), self::RESERVED_SUBDOMAINS, true);
    }

    private function hasTable(string $name): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
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
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(' . $this->pdo->quote($table) . ')');
            if ($stmt === false) {
                return false;
            }
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
                $mapping = [
                    'qr_label_line1' => 'qrLabelLine1',
                    'qr_label_line2' => 'qrLabelLine2',
                    'qr_logo_path' => 'qrLogoPath',
                    'qr_round_mode' => 'qrRoundMode',
                    'qr_logo_punchout' => 'qrLogoPunchout',
                    'qr_rounded' => 'qrRounded',
                    'qr_color_team' => 'qrColorTeam',
                    'qr_color_catalog' => 'qrColorCatalog',
                    'qr_color_event' => 'qrColorEvent',
                ];
                foreach ($mapping as $old => $new) {
                    if (array_key_exists($old, $cfg) && !array_key_exists($new, $cfg)) {
                        $cfg[$new] = $cfg[$old];
                    }
                    unset($cfg[$old]);
                }
                unset($cfg['id']);
                $filtered = [];
                foreach ($cfg as $k => $v) {
                    if ($this->hasColumn('config', strtolower($k))) {
                        $filtered[$k] = $v;
                    }
                }
                $filtered['event_uid'] = $activeUid;
                $cols = array_keys($filtered);
                $place = array_map(fn($c) => ':' . $c, $cols);
                $sql = 'INSERT INTO config(' . implode(',', $cols) . ') VALUES(' . implode(',', $place) . ')';
                $stmt = $this->pdo->prepare($sql);
                foreach ($filtered as $k => $v) {
                    if (is_bool($v)) {
                        $stmt->bindValue(':' . $k, $v, PDO::PARAM_BOOL);
                    } else {
                        $stmt->bindValue(':' . $k, $v);
                    }
                }
                $stmt->execute();
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
                    'uid,sort_order,slug,file,name,description,' .
                    'raetsel_buchstabe,comment,event_uid' .
                ') VALUES(?,?,?,?,?,?,?,?,?)'
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
        $stmt = $this->pdo->prepare('SELECT plan FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        $plan = $stmt->fetchColumn();
        return $plan === false ? null : (string) $plan;
    }

    /**
     * Retrieve custom limits for a tenant identified by subdomain.
     *
     * @return array<string,int|null>|null
     */
    public function getCustomLimitsBySubdomain(string $subdomain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT custom_limits FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        $json = $stmt->fetchColumn();
        if ($json === false || $json === null) {
            return null;
        }
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : null;
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
        return $plan !== null ? (Plan::tryFrom($plan)?->limits() ?? []) : [];
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
            'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, '
            . 'imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, '
            . 'plan_started_at, plan_expires_at, created_at '
            . 'FROM tenants WHERE subdomain = ?'
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
     * Retrieve the main-domain profile, seeding it from the legacy JSON file if necessary.
     *
     * @return array<string,mixed>
     */
    public function getMainTenant(): array
    {
        $tenant = $this->getBySubdomain('main');
        if ($tenant !== null) {
            return $tenant;
        }

        $path = dirname(__DIR__, 2) . '/data/profile.json';
        $data = [];
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true) ?? [];
        }
        $uid = (string) ($data['uid'] ?? bin2hex(random_bytes(16)));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants(' .
            'uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, ' .
            'imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, ' .
            'plan_started_at, plan_expires_at' .
            ') VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid,
            'main',
            $data['plan'] ?? null,
            $data['billing_info'] ?? null,
            $data['stripe_customer_id'] ?? null,
            $data['imprint_name'] ?? null,
            $data['imprint_street'] ?? null,
            $data['imprint_zip'] ?? null,
            $data['imprint_city'] ?? null,
            $data['imprint_email'] ?? null,
            null,
            $data['plan_started_at'] ?? null,
            $data['plan_expires_at'] ?? null,
        ]);

        return $this->getBySubdomain('main') ?? [];
    }

    /**
     * Update profile information for a tenant identified by subdomain.
     *
     * @param array<string,mixed> $data
     */
    public function updateProfile(string $subdomain, array $data): void
    {
        foreach ($data as &$value) {
            if ($value === '') {
                $value = null;
            }
        }
        unset($value);

        if (array_key_exists('plan', $data) && $data['plan'] !== null) {
            $planEnum = Plan::tryFrom((string) $data['plan']);
            if ($planEnum === null) {
                throw new \RuntimeException('invalid-plan');
            }
            $maxEvents = $planEnum->limits()['maxEvents'] ?? null;
            if ($maxEvents !== null && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $pdo = $this->pdo;
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $pdo->exec(sprintf('SET search_path TO "%s"', $subdomain));
                }
                try {
                    $count = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
                } finally {
                    if ($driver === 'pgsql') {
                        $pdo->exec('SET search_path TO public');
                    }
                }
                if ($count > $maxEvents) {
                    throw new \RuntimeException('max-events-exceeded');
                }
            }
        }

        $fields = [
            'plan',
            'billing_info',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_price_id',
            'stripe_status',
            'stripe_current_period_end',
            'stripe_cancel_at_period_end',
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
                $value = $data[$f];
                if ($f === 'plan' && $value !== null && Plan::tryFrom((string) $value) === null) {
                    throw new \RuntimeException('invalid-plan');
                }
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }
                $set[] = $f . ' = ?';
                $params[] = $f === 'custom_limits'
                    ? ($value !== null ? json_encode($value) : null)
                    : $value;
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
     * Remove Stripe association and plan for a given customer.
     */
    public function removeStripeCustomer(string $customerId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET stripe_customer_id = NULL, plan = NULL WHERE stripe_customer_id = ?'
        );
        $stmt->execute([$customerId]);
    }

    /**
     * Cancel the plan for a given customer.
     */
    public function cancelPlanForCustomer(string $customerId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET plan = NULL WHERE stripe_customer_id = ?'
        );
        $stmt->execute([$customerId]);
    }

    /**
     * Import tenant records for schemas that are missing in the tenants table.
     */
    public function importMissing(): int
    {
        $existing = $this->pdo
            ->query('SELECT subdomain FROM tenants')
            ->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $this->pdo->query(
            "SELECT schema_name FROM information_schema.schemata " .
            "WHERE schema_name NOT LIKE 'pg_%' " .
            "AND schema_name NOT IN ('information_schema','public')"
        );
        $schemas = [];
        $ins = $this->pdo->prepare('INSERT INTO tenants(uid, subdomain) VALUES(?, ?)');
        $count = 0;
        while (($schema = $stmt->fetchColumn()) !== false) {
            $schemas[] = $schema;
            if (in_array($schema, $existing, true)) {
                continue;
            }
            if ($this->isReserved($schema)) {
                continue;
            }
            $ins->execute([bin2hex(random_bytes(16)), $schema]);
            $existing[] = $schema;
            $count++;
        }

        $tenantsDir = dirname(__DIR__, 2) . '/tenants';
        if (is_dir($tenantsDir)) {
            foreach (scandir($tenantsDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (in_array($dir, $existing, true)) {
                    continue;
                }
                if ($this->isReserved($dir)) {
                    continue;
                }
                if (!in_array($dir, $schemas, true)) {
                    $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $dir));
                    $schemas[] = $dir;
                }
                $ins->execute([bin2hex(random_bytes(16)), $dir]);
                $existing[] = $dir;
                $count++;
            }
        }

        return $count;
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
    public function getAll(string $query = ''): array
    {
        $sql = 'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, '
            . 'stripe_subscription_id, stripe_status, imprint_name, imprint_street, imprint_zip, '
            . 'imprint_city, imprint_email, custom_limits, plan_started_at, '
            . 'plan_expires_at, created_at FROM tenants';
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE LOWER(subdomain) LIKE :q OR LOWER(imprint_name) LIKE :q OR LOWER(imprint_email) LIKE :q';
            $params[':q'] = '%' . strtolower($query) . '%';
        }
        $sql .= ' ORDER BY created_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($row['custom_limits'] !== null) {
                $row['custom_limits'] = json_decode((string) $row['custom_limits'], true);
            }
            $stripeStatus = (string) ($row['stripe_status'] ?? '');
            if ($stripeStatus === 'canceled') {
                $row['status'] = 'canceled';
            } elseif (!empty($row['plan'])) {
                $row['status'] = 'active';
            } else {
                $row['status'] = 'simulated';
            }
        }
        return $rows;
    }
}
