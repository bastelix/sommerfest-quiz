<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
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
    private string $tenantsDir;

    private const SYNC_SETTINGS_KEY = 'tenants:last_sync';
    private const SYNC_COOLDOWN_SECONDS = 300;
    private const SYNC_STALE_AFTER_SECONDS = 3600;

    /**
     * Subdomain used for the default "public" schema.
     */
    private const MAIN_SUBDOMAIN = 'main';
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

    public const ONBOARDING_PENDING = 'pending';
    public const ONBOARDING_PROVISIONING = 'provisioning';
    public const ONBOARDING_PROVISIONED = 'provisioned';
    public const ONBOARDING_COMPLETED = 'completed';
    public const ONBOARDING_FAILED = 'failed';

    private const ONBOARDING_STATES = [
        self::ONBOARDING_PENDING,
        self::ONBOARDING_PROVISIONING,
        self::ONBOARDING_PROVISIONED,
        self::ONBOARDING_COMPLETED,
        self::ONBOARDING_FAILED,
    ];

    public function __construct(
        ?PDO $pdo = null,
        ?string $migrationsDir = null,
        ?NginxService $nginxService = null,
        ?string $tenantsDir = null
    ) {
        $this->pdo = $pdo ?? Database::connectFromEnv();
        $this->migrationsDir = $migrationsDir ?? dirname(__DIR__, 2) . '/migrations';
        $this->nginxService = $nginxService;
        $this->tenantsDir = $this->resolveTenantsDir($tenantsDir);
    }

    private function resolveTenantsDir(?string $tenantsDir): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $envValue = getenv('TENANTS_DIR');
        $path = $tenantsDir ?? ($envValue !== false && $envValue !== '' ? $envValue : $projectRoot . '/tenants');

        if ($path === '') {
            return $projectRoot . '/tenants';
        }

        $path = str_replace('\\', '/', $path);

        if (!$this->isAbsolutePath($path)) {
            $path = rtrim($projectRoot, '/') . '/' . ltrim($path, '/');
        }

        $path = $this->normalisePath($path);
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('~^[A-Za-z]:[\\/]~', $path) === 1
            || str_starts_with($path, '\\');
    }

    private function normalisePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = explode('/', $path);
        $stack = [];
        $prefix = '';

        if (preg_match('~^[A-Za-z]:$~', $segments[0]) === 1) {
            $prefix = array_shift($segments) . '/';
        } elseif (str_starts_with($path, '//')) {
            $prefix = '//';
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($stack === []) {
                    continue;
                }

                array_pop($stack);
                continue;
            }

            $stack[] = $segment;
        }

        $normalised = $prefix . implode('/', $stack);

        return $normalised !== '' ? $normalised : ($prefix === '' ? '.' : $prefix);
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
        if ($this->isReserved($schema)) {
            throw new \RuntimeException('tenant-exists');
        }

        if ($plan !== null && Plan::tryFrom($plan) === null) {
            throw new \RuntimeException('invalid-plan');
        }

        $start = $plan !== null ? new DateTimeImmutable() : null;
        $end = $start?->modify('+30 days');
        $customLimitsJson = $customLimits !== null ? json_encode($customLimits) : null;
        $record = [
            'uid' => $uid,
            'subdomain' => $schema,
            'plan' => $plan,
            'billing_info' => $billing,
            'imprint_name' => $imprintName,
            'imprint_street' => $imprintStreet,
            'imprint_zip' => $imprintZip,
            'imprint_city' => $imprintCity,
            'imprint_email' => $email,
            'custom_limits' => $customLimitsJson,
            'plan_started_at' => $start?->format('Y-m-d H:i:sP'),
            'plan_expires_at' => $end?->format('Y-m-d H:i:sP'),
        ];

        $statePersisted = false;
        $hasExistingRecord = false;
        $schemaCreated = false;
        $lockKey = $this->acquireTenantLock($schema);

        try {
            $existing = $this->getBySubdomain($schema);
            if ($existing !== null) {
                $existingState = (string) ($existing['onboarding_state'] ?? self::ONBOARDING_COMPLETED);
                if ($existingState === self::ONBOARDING_COMPLETED) {
                    throw new \RuntimeException('tenant-exists');
                }
                $hasExistingRecord = true;
            }

            $this->persistTenantRecord($record, $hasExistingRecord, self::ONBOARDING_PROVISIONING);
            $statePersisted = true;
            $hasExistingRecord = true;

            $schemaAlreadyExists = $this->schemaExists($schema);
            $isNewSchema = !$schemaAlreadyExists;

            if ($isNewSchema) {
                try {
                    $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $schema));
                    $schemaCreated = true;
                } catch (PDOException $e) {
                    if ($e->getCode() === '42P06') {
                        $isNewSchema = false;
                    } else {
                        throw new \RuntimeException('schema-create-failed: ' . $e->getMessage(), 0, $e);
                    }
                }
            }

            try {
                $this->pdo->exec(sprintf('SET search_path TO "%s", public', $schema));
                Migrator::migrate($this->pdo, $this->migrationsDir);
                if ($isNewSchema) {
                    $this->seedDemoData();
                }
            } catch (PDOException $e) {
                if ($this->isDuplicateColumnError($e)) {
                    error_log('Duplicate column detected during migration for schema ' . $schema . ': ' . $e->getMessage());
                } else {
                    throw new \RuntimeException('migration-failed: ' . $e->getMessage(), 0, $e);
                }
            } finally {
                $this->pdo->exec('SET search_path TO public');
            }

            $this->persistTenantRecord($record, $hasExistingRecord, self::ONBOARDING_PROVISIONED);

            if ($this->nginxService !== null) {
                try {
                    $this->nginxService->createVhost($schema);
                } catch (\Throwable $e) {
                    try {
                        $this->nginxService->removeVhost($schema, false);
                    } catch (\Throwable $inner) {
                        error_log('Failed to remove nginx vhost after error: ' . $inner->getMessage());
                    }
                    error_log('Failed to reload nginx: ' . $e->getMessage());
                    throw new \RuntimeException('Nginx reload failed – check Docker installation', 0, $e);
                }
            }
        } catch (\Throwable $e) {
            if ($statePersisted || $hasExistingRecord) {
                try {
                    $this->updateOnboardingState($schema, self::ONBOARDING_FAILED);
                } catch (\Throwable $inner) {
                    error_log('Failed to mark tenant onboarding as failed: ' . $inner->getMessage());
                }
            }
            if ($schemaCreated) {
                try {
                    $this->pdo->exec(sprintf('DROP SCHEMA "%s" CASCADE', $schema));
                } catch (\Throwable $inner) {
                    error_log('Failed to drop tenant schema after error: ' . $inner->getMessage());
                }
            }
            throw $e;
        } finally {
            $this->releaseTenantLock($lockKey);
        }
    }

    /**
     * Drop the tenant schema and remove its record.
     */
    public function deleteTenant(string $uid): void {
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
    public function exists(string $subdomain): bool {
        if ($this->isReserved($subdomain)) {
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT onboarding_state FROM tenants WHERE subdomain = ?');
        $stmt->execute([$subdomain]);
        $state = $stmt->fetchColumn();
        if ($state !== false) {
            return (string) $state !== self::ONBOARDING_FAILED;
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

    protected function schemaExists(string $schema): bool {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $e) {
            return false;
        }

        if ($driver === 'sqlite') {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.schemata WHERE schema_name = ? LIMIT 1'
            );
            $stmt->execute([$schema]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function isReserved(string $subdomain): bool {
        $normalised = strtolower($subdomain);

        if ($normalised === self::MAIN_SUBDOMAIN) {
            return true;
        }

        return in_array($normalised, self::RESERVED_SUBDOMAINS, true);
    }

    private function hasTable(string $name): bool {
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

    private function hasColumn(string $table, string $column): bool {
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
     * @param array{
     *   uid:string,
     *   subdomain:string,
     *   plan:?string,
     *   billing_info:?string,
     *   imprint_name:?string,
     *   imprint_street:?string,
     *   imprint_zip:?string,
     *   imprint_city:?string,
     *   imprint_email:?string,
     *   custom_limits:?string,
     *   plan_started_at:?string,
     *   plan_expires_at:?string
     * } $record
     */
    private function persistTenantRecord(array $record, bool $exists, string $state): void {
        if (!in_array($state, self::ONBOARDING_STATES, true)) {
            throw new \InvalidArgumentException('invalid-onboarding-state');
        }

        if ($exists) {
            $stmt = $this->pdo->prepare(
                'UPDATE tenants SET '
                . 'uid = ?, plan = ?, billing_info = ?, imprint_name = ?, imprint_street = ?, imprint_zip = ?, '
                . 'imprint_city = ?, imprint_email = ?, custom_limits = ?, onboarding_state = ?, plan_started_at = ?, '
                . 'plan_expires_at = ? WHERE subdomain = ?'
            );
            $stmt->execute([
                $record['uid'],
                $record['plan'],
                $record['billing_info'],
                $record['imprint_name'],
                $record['imprint_street'],
                $record['imprint_zip'],
                $record['imprint_city'],
                $record['imprint_email'],
                $record['custom_limits'],
                $state,
                $record['plan_started_at'],
                $record['plan_expires_at'],
                $record['subdomain'],
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants('
            . 'uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, imprint_street, '
            . 'imprint_zip, imprint_city, imprint_email, custom_limits, onboarding_state, plan_started_at, plan_expires_at'
            . ') VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $record['uid'],
            $record['subdomain'],
            $record['plan'],
            $record['billing_info'],
            null,
            $record['imprint_name'],
            $record['imprint_street'],
            $record['imprint_zip'],
            $record['imprint_city'],
            $record['imprint_email'],
            $record['custom_limits'],
            $state,
            $record['plan_started_at'],
            $record['plan_expires_at'],
        ]);
    }

    private function acquireTenantLock(string $subdomain): ?int {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $e) {
            return null;
        }

        if ($driver !== 'pgsql') {
            return null;
        }

        $lockKey = (int) sprintf('%u', crc32('tenant_create_' . $subdomain));
        $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key)');
        $stmt->execute([':key' => $lockKey]);

        return $lockKey;
    }

    private function releaseTenantLock(?int $lockKey): void {
        if ($lockKey === null) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key)');
            $stmt->execute([':key' => $lockKey]);
        } catch (PDOException $e) {
            error_log('Failed to release advisory lock: ' . $e->getMessage());
        }
    }

    private function isDuplicateColumnError(PDOException $exception): bool {
        if ($exception->getCode() === '42701') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate column')
            || str_contains($message, 'already exists');
    }

    /**
     * Seed demo data from the bundled data directory into the current schema.
     */
    private function seedDemoData(): void {
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
            $hasSlug = $this->hasColumn('events', 'slug');
            $sql = $hasSlug
                ? 'INSERT INTO events(uid,slug,name,start_date,end_date,description) VALUES(?,?,?,?,?,?)'
                : 'INSERT INTO events(uid,name,start_date,end_date,description) VALUES(?,?,?,?,?)';
            $stmt = $this->pdo->prepare($sql);
            foreach ($events as $e) {
                $uid = $e['uid'] ?? bin2hex(random_bytes(16));
                if ($activeUid === null) {
                    $activeUid = $uid;
                }
                $params = [$uid];
                if ($hasSlug) {
                    $params[] = $e['slug'] ?? $uid;
                }
                $params[] = $e['name'] ?? '';
                $params[] = $e['start_date'] ?? date('Y-m-d\TH:i');
                $params[] = $e['end_date'] ?? date('Y-m-d\TH:i');
                $params[] = $e['description'] ?? null;
                $stmt->execute($params);
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
            $qStmt = null;
            $hasCards = false;
            $hasRightLabel = false;
            $hasLeftLabel = false;
            $hasCountdown = false;
            if ($this->hasTable('questions')) {
                $hasCards = $this->hasColumn('questions', 'cards');
                $hasRightLabel = $this->hasColumn('questions', 'right_label');
                $hasLeftLabel = $this->hasColumn('questions', 'left_label');
                $hasCountdown = $this->hasColumn('questions', 'countdown');
                $columns = ['catalog_uid', 'type', 'prompt', 'options', 'answers', 'terms', 'items'];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
                if ($hasCards) {
                    $columns[] = 'cards';
                    $placeholders[] = '?';
                }
                if ($hasRightLabel) {
                    $columns[] = 'right_label';
                    $placeholders[] = '?';
                }
                if ($hasLeftLabel) {
                    $columns[] = 'left_label';
                    $placeholders[] = '?';
                }
                $columns[] = 'sort_order';
                $placeholders[] = '?';
                if ($hasCountdown) {
                    $columns[] = 'countdown';
                    $placeholders[] = '?';
                }
                $qStmt = $this->pdo->prepare(
                    'INSERT INTO questions(' . implode(',', $columns) . ') VALUES(' . implode(',', $placeholders) . ')'
                );
            }
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
                            $params = [
                                $cat['uid'] ?? '',
                                $q['type'] ?? '',
                                $q['prompt'] ?? '',
                                isset($q['options']) ? json_encode($q['options']) : null,
                                isset($q['answers']) ? json_encode($q['answers']) : null,
                                isset($q['terms']) ? json_encode($q['terms']) : null,
                                isset($q['items']) ? json_encode($q['items']) : null,
                            ];
                            if ($hasCards) {
                                $params[] = isset($q['cards']) ? json_encode($q['cards']) : null;
                            }
                            if ($hasRightLabel) {
                                $params[] = $q['rightLabel'] ?? null;
                            }
                            if ($hasLeftLabel) {
                                $params[] = $q['leftLabel'] ?? null;
                            }
                            $params[] = $i + 1;
                            if ($hasCountdown) {
                                $params[] = array_key_exists('countdown', $q)
                                    ? (is_numeric($q['countdown']) ? (int) $q['countdown'] : null)
                                    : null;
                            }
                            $qStmt->execute($params);
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
    public function getPlanBySubdomain(string $subdomain): ?string {
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
    public function getCustomLimitsBySubdomain(string $subdomain): ?array {
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
    public function setCustomLimits(string $subdomain, ?array $limits): void {
        $stmt = $this->pdo->prepare('UPDATE tenants SET custom_limits = ? WHERE subdomain = ?');
        $stmt->execute([$limits !== null ? json_encode($limits) : null, $subdomain]);
    }

    /**
     * Retrieve effective limits for a tenant.
     *
     * @return array<string,int|null>
     */
    public function getLimitsBySubdomain(string $subdomain): array {
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
     *   onboarding_state:?string,
     *   created_at:string
     * }|null
     */
    public function getBySubdomain(string $subdomain): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, '
            . 'imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, '
            . 'plan_started_at, plan_expires_at, onboarding_state, created_at '
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
     * Retrieve namespace-specific profile data with a fallback to the default namespace.
     *
     * @return array<string,mixed>
     */
    public function getNamespaceProfile(string $namespace): array {
        $normalized = $this->normalizeNamespace($namespace);
        $profile = $this->fetchNamespaceProfile($normalized);

        if ($profile === null && $normalized !== PageService::DEFAULT_NAMESPACE) {
            $profile = $this->fetchNamespaceProfile(PageService::DEFAULT_NAMESPACE);
        }

        return $profile ?? $this->getMainTenant();
    }

    /**
     * Retrieve the main-domain profile, seeding it from the legacy JSON file if necessary.
     *
     * @return array<string,mixed>
     */
    public function getMainTenant(): array {
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
            'uid, subdomain, plan, billing_info, stripe_customer_id, imprint_name, '
            . 'imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, '
            . 'onboarding_state, plan_started_at, plan_expires_at'
            . ') VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            self::ONBOARDING_COMPLETED,
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
    public function updateProfile(string $subdomain, array $data): void {
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

        if ($subdomain === self::MAIN_SUBDOMAIN) {
            $this->syncNamespaceProfile(PageService::DEFAULT_NAMESPACE, $data);
        }
    }

    public function updateOnboardingState(string $subdomain, string $state): void {
        if (!in_array($state, self::ONBOARDING_STATES, true)) {
            throw new \InvalidArgumentException('invalid-onboarding-state');
        }
        $stmt = $this->pdo->prepare('UPDATE tenants SET onboarding_state = ? WHERE subdomain = ?');
        $stmt->execute([$state, $subdomain]);
    }

    private function fetchNamespaceProfile(string $namespace): ?array {
        if (!$this->hasTable('namespace_profile')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT namespace, imprint_name, imprint_street, imprint_zip, imprint_city, imprint_email '
            . 'FROM namespace_profile WHERE namespace = ?'
        );
        $stmt->execute([$namespace]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function normalizeNamespace(string $namespace): string {
        $normalized = strtolower(trim($namespace));

        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function syncNamespaceProfile(string $namespace, array $data): void {
        if (!$this->hasTable('namespace_profile')) {
            return;
        }

        $fields = [
            'imprint_name',
            'imprint_street',
            'imprint_zip',
            'imprint_city',
            'imprint_email',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($payload === []) {
            return;
        }

        $columns = array_merge(['namespace'], array_keys($payload));
        $placeholders = array_fill(0, count($columns), '?');
        $updates = [];
        foreach (array_keys($payload) as $field) {
            $updates[] = $field . ' = EXCLUDED.' . $field;
        }

        $sql = sprintf(
            'INSERT INTO namespace_profile (%s) VALUES (%s) ON CONFLICT (namespace) DO UPDATE SET %s',
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$namespace], array_values($payload)));
    }

    /**
     * Update a tenant identified by its Stripe customer id.
     *
     * @param array<string,mixed> $data
     */
    public function updateByStripeCustomerId(string $customerId, array $data): void {
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
    public function removeStripeCustomer(string $customerId): void {
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET stripe_customer_id = NULL, plan = NULL WHERE stripe_customer_id = ?'
        );
        $stmt->execute([$customerId]);
    }

    /**
     * Cancel the plan for a given customer.
     */
    public function cancelPlanForCustomer(string $customerId): void {
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET plan = NULL WHERE stripe_customer_id = ?'
        );
        $stmt->execute([$customerId]);
    }

    /**
     * Import tenant records for schemas that are missing in the tenants table.
     *
     * @return array{
     *   imported:int,
     *   throttled:bool,
     *   sync:array{
     *     last_run_at:?string,
     *     next_allowed_at:?string,
     *     cooldown_seconds:int,
     *     stale_after_seconds:int,
     *     is_stale:bool,
     *     is_throttled:bool
     *   }
     * }
     */
    public function importMissing(): array {
        $now = new DateTimeImmutable();
        $lastRun = $this->fetchLastSync();
        $cooldownEnd = $lastRun?->modify('+' . self::SYNC_COOLDOWN_SECONDS . ' seconds');
        if ($cooldownEnd !== null && $cooldownEnd > $now) {
            return [
                'imported' => 0,
                'throttled' => true,
                'sync' => $this->buildSyncState($lastRun, $now),
            ];
        }

        $existing = $this->pdo
            ->query('SELECT subdomain FROM tenants')
            ->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $this->pdo->query(
            "SELECT schema_name FROM information_schema.schemata " .
            "WHERE schema_name NOT LIKE 'pg_%' " .
            "AND schema_name NOT IN ('information_schema')"
        );
        $schemas = [];
        $ins = $this->pdo->prepare('INSERT INTO tenants(uid, subdomain, onboarding_state) VALUES(?, ?, ?)');
        $count = 0;
        while (($schema = $stmt->fetchColumn()) !== false) {
            $schemas[] = $schema;
            $subdomain = $schema === 'public' ? self::MAIN_SUBDOMAIN : $schema;
            if (in_array($subdomain, $existing, true)) {
                continue;
            }
            if ($this->isReserved($subdomain)) {
                continue;
            }
            $ins->execute([bin2hex(random_bytes(16)), $subdomain, self::ONBOARDING_COMPLETED]);
            $existing[] = $subdomain;
            $count++;
        }

        $tenantsDir = $this->tenantsDir;
        if (is_dir($tenantsDir)) {
            foreach (scandir($tenantsDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $schemaName = $dir === self::MAIN_SUBDOMAIN ? 'public' : $dir;
                if (in_array($dir, $existing, true)) {
                    continue;
                }
                if ($this->isReserved($dir)) {
                    continue;
                }
                if (!in_array($schemaName, $schemas, true)) {
                    $this->pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
                    $schemas[] = $schemaName;
                }
                $ins->execute([bin2hex(random_bytes(16)), $dir, self::ONBOARDING_COMPLETED]);
                $existing[] = $dir;
                $count++;
            }
        }

        $this->persistLastSync($now);

        return [
            'imported' => $count,
            'throttled' => false,
            'sync' => $this->buildSyncState($now, $now),
        ];
    }

    /**
     * Provide metadata about the last tenant import.
     *
     * @return array{
     *   last_run_at:?string,
     *   next_allowed_at:?string,
     *   cooldown_seconds:int,
     *   stale_after_seconds:int,
     *   is_stale:bool,
     *   is_throttled:bool
     * }
     */
    public function getSyncState(): array {
        return $this->buildSyncState($this->fetchLastSync());
    }

    /**
     * Persist the timestamp of the last tenant import.
     */
    private function persistLastSync(DateTimeImmutable $time): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO settings(key, value) VALUES(?, ?) '
                . 'ON CONFLICT(key) DO UPDATE SET value = excluded.value'
            );
            $stmt->execute([self::SYNC_SETTINGS_KEY, $time->format(DATE_ATOM)]);
        } catch (PDOException $e) {
            // ignore missing settings table – sync throttling will simply be disabled
        }
    }

    private function fetchLastSync(): ?DateTimeImmutable {
        try {
            $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key = ?');
            $stmt->execute([self::SYNC_SETTINGS_KEY]);
            $value = $stmt->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array{
     *   last_run_at:?string,
     *   next_allowed_at:?string,
     *   cooldown_seconds:int,
     *   stale_after_seconds:int,
     *   is_stale:bool,
     *   is_throttled:bool
     * }
     */
    private function buildSyncState(?DateTimeImmutable $lastRun, ?DateTimeImmutable $now = null): array {
        $now ??= new DateTimeImmutable();
        $nextAllowed = $lastRun?->modify('+' . self::SYNC_COOLDOWN_SECONDS . ' seconds');
        $staleAt = $lastRun?->modify('+' . self::SYNC_STALE_AFTER_SECONDS . ' seconds');
        $isThrottled = $nextAllowed !== null && $nextAllowed > $now;
        $isStale = $lastRun === null || ($staleAt !== null && $staleAt < $now);

        return [
            'last_run_at' => $lastRun?->format(DATE_ATOM),
            'next_allowed_at' => $nextAllowed?->format(DATE_ATOM),
            'cooldown_seconds' => self::SYNC_COOLDOWN_SECONDS,
            'stale_after_seconds' => self::SYNC_STALE_AFTER_SECONDS,
            'is_stale' => $isStale,
            'is_throttled' => $isThrottled,
        ];
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
    public function getAll(string $query = ''): array {
        $sql = 'SELECT uid, subdomain, plan, billing_info, stripe_customer_id, '
            . 'stripe_subscription_id, stripe_status, onboarding_state, imprint_name, imprint_street, imprint_zip, '
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
            $onboardingState = (string) ($row['onboarding_state'] ?? self::ONBOARDING_COMPLETED);
            $row['onboarding_state'] = $onboardingState;
            if (
                in_array($onboardingState, [
                self::ONBOARDING_PENDING,
                self::ONBOARDING_PROVISIONING,
                self::ONBOARDING_FAILED,
                self::ONBOARDING_PROVISIONED,
                ], true)
            ) {
                $row['status'] = $onboardingState;
                continue;
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
