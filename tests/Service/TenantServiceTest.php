<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\TenantService;
use Tests\TestCase;
use PDO;
use App\Domain\Plan;
use App\Infrastructure\Migrations\Migrator;

class TenantServiceTest extends TestCase
{
    private function makeRelativePath(string $from, string $to): string
    {
        $from = str_replace('\\', '/', rtrim($from, '/'));
        $to = str_replace('\\', '/', rtrim($to, '/'));

        $fromSegments = $from === '' ? [] : explode('/', ltrim($from, '/'));
        $toSegments = $to === '' ? [] : explode('/', ltrim($to, '/'));

        $common = 0;
        $max = min(count($fromSegments), count($toSegments));
        while ($common < $max && $fromSegments[$common] === $toSegments[$common]) {
            $common++;
        }

        $up = array_fill(0, count($fromSegments) - $common, '..');
        $down = array_slice($toSegments, $common);
        $parts = array_merge($up, $down);

        $relative = implode('/', $parts);

        return $relative !== '' ? $relative : '.';
    }

    private function createService(
        string $dir,
        PDO &$pdo,
        ?\App\Service\NginxService $nginx = null,
        ?callable $factory = null
    ): TenantService {
        $pdo = new class ('sqlite::memory:') extends PDO {
            /** @var list<string> */
            public array $executedStatements = [];

            public function __construct(string $dsn) {
                parent::__construct($dsn);
            }

            public function exec($statement): int|false {
                $this->executedStatements[] = $statement;

                if (
                    preg_match('/^(CREATE|DROP) SCHEMA/i', $statement)
                    || str_starts_with($statement, 'SET search_path')
                ) {
                    return 0;
                }

                return parent::exec($statement);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
            'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
            'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
            'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
            'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)'
        );
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $sql = <<<'SQL'
CREATE TABLE events(uid TEXT PRIMARY KEY, slug TEXT UNIQUE NOT NULL, sort_order INTEGER DEFAULT 0);
CREATE TABLE catalogs(
    uid TEXT PRIMARY KEY,
    sort_order INTEGER,
    slug TEXT,
    file TEXT,
    name TEXT,
    event_uid TEXT
);
CREATE TABLE question_results(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    catalog TEXT,
    question_id INTEGER,
    attempt INTEGER,
    correct INTEGER,
    points INTEGER NOT NULL DEFAULT 0,
    time_left_sec INTEGER,
    final_points INTEGER NOT NULL DEFAULT 0,
    efficiency REAL NOT NULL DEFAULT 0,
    is_correct INTEGER,
    scoring_version INTEGER NOT NULL DEFAULT 1,
    answer_text TEXT,
    photo TEXT,
    consent INTEGER,
    event_uid TEXT
);
SQL;
        file_put_contents($dir . '/20240910_base_schema.sql', $sql);
        Migrator::setHook(static function (PDO $hookPdo, string $hookDir) use ($pdo, $dir): bool {
            if ($hookPdo === $pdo && $hookDir === $dir) {
                $hookPdo->exec('CREATE TABLE IF NOT EXISTS migrations(version TEXT PRIMARY KEY)');
                $stmt = $hookPdo->prepare('INSERT OR IGNORE INTO migrations(version) VALUES(?)');
                $stmt->execute(['20240910_base_schema.sql']);
                return false;
            }
            return true;
        });
        if ($nginx === null) {
            $nginx = new class extends \App\Service\NginxService {
                public function __construct() {
                }

                public function createVhost(string $sub): void {
                }

                public function removeVhost(string $sub, ?bool $triggerReload = null): void
                {
                }
            };
        }
        if ($factory !== null) {
            /** @var TenantService $service */
            $service = $factory($pdo, $dir, $nginx);
            return $service;
        }

        return new TenantService($pdo, $dir, $nginx);
    }

    public function testCreateTenantInsertsRow(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u1', 's1', null, null, 'u1@example.com', null, null, null, null);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(1, $count);
        $email = $pdo->query("SELECT imprint_email FROM tenants WHERE uid='u1'")->fetchColumn();
        $this->assertSame('u1@example.com', $email);
    }

    public function testCreateTenantRunsMigrations(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('ux', 'sx');
        $applied = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('20240910_base_schema.sql', $applied);
    }

    public function testDeleteTenantRemovesRow(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u2', 's2');
        $service->deleteTenant('u2');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testCreateAndDeleteSequence(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u3', 's3');
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn());
        $service->deleteTenant('u3');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn());
    }

    public function testGetAllFiltersByQuery(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, imprint_name, imprint_email, created_at) "
            . "VALUES('u1','alpha','Alpha GmbH','a@example.com','2024-01-01')"
        );
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, imprint_name, imprint_email, created_at) "
            . "VALUES('u2','beta','Beta AG','b@example.com','2024-01-02')"
        );
        $list = $service->getAll('beta');
        $this->assertCount(1, $list);
        $this->assertSame('beta', $list[0]['subdomain']);
    }

    public function testGetAllMapsOnboardingStatesToStatus(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO tenants(uid, subdomain, onboarding_state, plan, stripe_status, created_at) '
            . 'VALUES(:uid, :subdomain, :state, :plan, :stripe_status, :created_at)'
        );

        $rows = [
            ['uid' => 'pending', 'subdomain' => 'pending', 'state' => TenantService::ONBOARDING_PENDING, 'plan' => null, 'stripe_status' => null, 'created_at' => '2024-02-01T00:00:00Z'],
            ['uid' => 'provisioning', 'subdomain' => 'provisioning', 'state' => TenantService::ONBOARDING_PROVISIONING, 'plan' => null, 'stripe_status' => null, 'created_at' => '2024-02-02T00:00:00Z'],
            ['uid' => 'provisioned', 'subdomain' => 'provisioned', 'state' => TenantService::ONBOARDING_PROVISIONED, 'plan' => null, 'stripe_status' => null, 'created_at' => '2024-02-03T00:00:00Z'],
            ['uid' => 'failed', 'subdomain' => 'failed', 'state' => TenantService::ONBOARDING_FAILED, 'plan' => null, 'stripe_status' => null, 'created_at' => '2024-02-04T00:00:00Z'],
            ['uid' => 'active', 'subdomain' => 'active', 'state' => TenantService::ONBOARDING_COMPLETED, 'plan' => Plan::STARTER->value, 'stripe_status' => null, 'created_at' => '2024-02-05T00:00:00Z'],
            ['uid' => 'canceled', 'subdomain' => 'canceled', 'state' => TenantService::ONBOARDING_COMPLETED, 'plan' => Plan::STARTER->value, 'stripe_status' => 'canceled', 'created_at' => '2024-02-06T00:00:00Z'],
            ['uid' => 'simulated', 'subdomain' => 'simulated', 'state' => TenantService::ONBOARDING_COMPLETED, 'plan' => null, 'stripe_status' => null, 'created_at' => '2024-02-07T00:00:00Z'],
        ];

        foreach ($rows as $row) {
            $stmt->execute([
                ':uid' => $row['uid'],
                ':subdomain' => $row['subdomain'],
                ':state' => $row['state'],
                ':plan' => $row['plan'],
                ':stripe_status' => $row['stripe_status'],
                ':created_at' => $row['created_at'],
            ]);
        }

        $list = $service->getAll();
        $byUid = [];
        foreach ($list as $row) {
            $byUid[$row['uid']] = $row;
        }

        $this->assertSame(TenantService::ONBOARDING_PENDING, $byUid['pending']['onboarding_state']);
        $this->assertSame(TenantService::ONBOARDING_PENDING, $byUid['pending']['status']);
        $this->assertSame(TenantService::ONBOARDING_PROVISIONING, $byUid['provisioning']['status']);
        $this->assertSame(TenantService::ONBOARDING_PROVISIONED, $byUid['provisioned']['status']);
        $this->assertSame(TenantService::ONBOARDING_FAILED, $byUid['failed']['status']);
        $this->assertSame('active', $byUid['active']['status']);
        $this->assertSame('canceled', $byUid['canceled']['status']);
        $this->assertSame('simulated', $byUid['simulated']['status']);
    }

    public function testRollbackRemovesSchemaAndVhostOnFailure(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $vhostDir = sys_get_temp_dir() . '/vhosts' . uniqid();
        $pdo = new PDO('sqlite::memory:');

        $nginx = new class ($vhostDir) extends \App\Service\NginxService {
            public array $removed = [];

            public function __construct(private string $dir) {
                parent::__construct($dir, 'example.test', '1m', false);
            }

            public function createVhost(string $sub): void
            {
                parent::createVhost($sub);
                throw new \RuntimeException('simulated-nginx-failure');
            }

            public function removeVhost(string $sub, ?bool $triggerReload = null): void
            {
                $this->removed[] = [$sub, $triggerReload];
                parent::removeVhost($sub, $triggerReload);
            }
        };

        $service = $this->createService($dir, $pdo, $nginx);

        try {
            $service->createTenant('u-fail', 'schemafail');
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Nginx reload failed', $e->getMessage());
        }

        /** @var object{executedStatements: list<string>} $pdo */
        $dropStatements = array_filter(
            $pdo->executedStatements,
            static fn (string $statement): bool => str_starts_with($statement, 'DROP SCHEMA "schemafail"')
        );

        $this->assertNotEmpty($dropStatements, 'Expected DROP SCHEMA to be executed');

        $vhostPath = $vhostDir . '/schemafail.example.test';
        $this->assertFileDoesNotExist($vhostPath);

        $this->assertSame([
            ['schemafail', false],
        ], $nginx->removed);
    }

    public function testCreateTenantThrowsOnNginxFailure(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $nginx = new class extends \App\Service\NginxService {
            public function __construct() {
            }

            public function createVhost(string $sub): void {
                throw new \RuntimeException('reload failed');
            }
        };

        $service = $this->createService($dir, $pdo, $nginx);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nginx reload failed');

        $service->createTenant('u4', 's4');
    }

    public function testCreateTenantFailsOnDuplicate(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u5', 'dup');
        $pdo->exec("UPDATE tenants SET onboarding_state='completed' WHERE subdomain='dup'");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant-exists');

        $service->createTenant('u5b', 'dup');
    }

    public function testCreateTenantHandlesExistingSchemaWithoutRecord(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');

        $service = $this->createService(
            $dir,
            $pdo,
            null,
            static function (PDO $pdoArg, string $migDir, \App\Service\NginxService $nginx): TenantService {
                return new class ($pdoArg, $migDir, $nginx) extends TenantService {
                    protected function schemaExists(string $schema): bool
                    {
                        if ($schema === 'ghost') {
                            return true;
                        }

                        return parent::schemaExists($schema);
                    }
                };
            }
        );

        $service->createTenant('ghost-uid', 'ghost');

        $row = $pdo
            ->query("SELECT uid, subdomain, onboarding_state FROM tenants WHERE subdomain='ghost'")
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('ghost-uid', $row['uid']);
        $this->assertSame('ghost', $row['subdomain']);
        $this->assertSame('provisioned', $row['onboarding_state']);
    }

    public function testCreateTenantAllowsRetryAfterFailure(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u6', 'retry');
        $pdo->exec("UPDATE tenants SET onboarding_state='failed' WHERE subdomain='retry'");

        $service->createTenant('u6b', 'retry', 'starter');

        $row = $pdo
            ->query("SELECT uid, onboarding_state, plan FROM tenants WHERE subdomain='retry'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('u6b', $row['uid']);
        $this->assertSame('provisioned', $row['onboarding_state']);
        $this->assertSame('starter', $row['plan']);
    }

    public function testCreateTenantContinuesOnDuplicateColumnError(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        Migrator::setHook(static function (PDO $hookPdo, string $hookDir) use ($pdo, $dir): bool {
            if ($hookPdo === $pdo && $hookDir === $dir) {
                throw new \PDOException('duplicate column "onboarding_state"', '42701');
            }

            return true;
        });

        try {
            $service->createTenant('dup-col', 'dupcol');
        } finally {
            Migrator::setHook(null);
        }

        $state = $pdo
            ->query("SELECT onboarding_state FROM tenants WHERE subdomain='dupcol'")
            ->fetchColumn();

        $this->assertSame('provisioned', $state);
    }

    public function testCreateTenantMarksFailureOnMigrationError(): void
    {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        Migrator::setHook(static function (PDO $hookPdo, string $hookDir) use ($pdo, $dir): bool {
            if ($hookPdo === $pdo && $hookDir === $dir) {
                throw new \PDOException('boom', 'XX000');
            }

            return true;
        });

        try {
            $service->createTenant('fail-col', 'failcol');
            $this->fail('Expected migration failure to throw an exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('migration-failed', $e->getMessage());
        } finally {
            Migrator::setHook(null);
        }

        $state = $pdo
            ->query("SELECT onboarding_state FROM tenants WHERE subdomain='failcol'")
            ->fetchColumn();

        $this->assertSame('failed', $state);
    }

    public function testExistsReturnsTrueForReserved(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $this->assertTrue($service->exists('www'));
    }

    public function testExistsReturnsFalseIfOnlySchemaExists(): void {
        $pdo = new class ('sqlite::memory:') extends PDO
        {
            public function __construct($dsn) {
                parent::__construct($dsn);
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT, onboarding_state TEXT DEFAULT "pending")');
        $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
        $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('orphan')");
        $service = new TenantService($pdo);
        $this->assertFalse($service->exists('orphan'));
    }

    public function testExistsReturnsTrueIfTablesExist(): void {
        $pdo = new class ('sqlite::memory:') extends PDO
        {
            public function __construct($dsn) {
                parent::__construct($dsn);
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE tenants(uid TEXT PRIMARY KEY, subdomain TEXT, onboarding_state TEXT DEFAULT "pending")');
        $pdo->exec('CREATE TABLE information_schema.tables(table_schema TEXT)');
        $pdo->exec("INSERT INTO information_schema.tables(table_schema) VALUES('busy')");
        $service = new TenantService($pdo);
        $this->assertTrue($service->exists('busy'));
    }

    public function testCreateTenantFailsOnReserved(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant-exists');

        $service->createTenant('uid', 'www');
    }

    public function testGetBySubdomainReturnsTenant(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, billing_info, imprint_name, " .
            "imprint_street, imprint_zip, imprint_city, imprint_email, custom_limits, onboarding_state, created_at) " .
            "VALUES('u6', 'sub', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'completed', '2024-01-01')"
        );
        $row = $service->getBySubdomain('sub');
        $this->assertIsArray($row);
        $this->assertSame('sub', $row['subdomain']);
    }

    public function testCreateTenantRejectsInvalidPlan(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid-plan');

        $service->createTenant('u7', 'sub7', 'unknown');
    }

    public function testUpdateProfileRejectsInvalidPlan(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u8', 'sub8', 'starter');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid-plan');

        $service->updateProfile('sub8', ['plan' => 'foo']);
    }

    public function testGetPlanBySubdomainReturnsPlan(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u9', 'sub9', 'starter');

        $plan = $service->getPlanBySubdomain('sub9');
        $this->assertSame('starter', $plan);
    }

    public function testCustomLimitsReadWrite(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u10', 'sub10', 'starter', null, null, null, null, null, null, ['maxEvents' => 2]);
        $limits = $service->getCustomLimitsBySubdomain('sub10');
        $this->assertSame(['maxEvents' => 2], $limits);
        $service->setCustomLimits('sub10', ['maxEvents' => 5]);
        $limits2 = $service->getCustomLimitsBySubdomain('sub10');
        $this->assertSame(['maxEvents' => 5], $limits2);
    }

    public function testPlanAndLimitsReflectExternalUpdates(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u12', 'sub12', Plan::STARTER->value);
        $this->assertSame(Plan::STARTER->value, $service->getPlanBySubdomain('sub12'));

        $webhook = new TenantService($pdo, $dir, new class extends \App\Service\NginxService {
            public function __construct() {
            }

            public function createVhost(string $sub): void {
            }
        });
        $webhook->updateProfile('sub12', ['plan' => Plan::STANDARD->value]);
        $this->assertSame(Plan::STANDARD->value, $service->getPlanBySubdomain('sub12'));
        $this->assertSame(Plan::STANDARD->limits(), $service->getLimitsBySubdomain('sub12'));

        $webhook->setCustomLimits('sub12', ['maxEvents' => 4]);
        $this->assertSame(['maxEvents' => 4], $service->getLimitsBySubdomain('sub12'));
    }

    public function testPlanDowngradeFailsWhenEventsExceedLimit(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            $this->markTestSkipped('Requires pgsql driver');
        }
        $service->createTenant('u13', 'sub13', Plan::STANDARD->value);
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('e1','e1','E1')");
        $pdo->exec("INSERT INTO events(uid, slug, name) VALUES('e2','e2','E2')");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-events-exceeded');

        $service->updateProfile('sub13', ['plan' => Plan::STARTER->value]);
    }

    public function testUpdateProfileRecalculatesExpiry(): void {
        $dir = sys_get_temp_dir() . '/mig' . uniqid();
        $pdo = new PDO('sqlite::memory:');
        $service = $this->createService($dir, $pdo);
        $service->createTenant('u11', 'sub11', 'starter');
        $service->updateProfile('sub11', ['plan_started_at' => '2000-01-01 00:00:00+00:00', 'plan' => 'starter']);
        $row = $pdo
            ->query("SELECT plan_started_at, plan_expires_at FROM tenants WHERE subdomain='sub11'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2000-01-01 00:00:00+00:00', $row['plan_started_at']);
        $this->assertSame('2000-01-31 00:00:00+00:00', $row['plan_expires_at']);
    }

    public function testImportMissingCreatesTenants(): void {
        $pdo = new class extends PDO {
            public function __construct() {
                parent::__construct('sqlite::memory:');
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
            'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
            'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
            'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
            'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
        $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('s1'),('public'),('s2')");
        $service = new TenantService($pdo);
        $result = $service->importMissing();
        $this->assertSame(3, $result['imported']);
        $this->assertFalse($result['throttled']);
        $this->assertArrayHasKey('sync', $result);
        $this->assertNotEmpty($result['sync']['last_run_at']);
        $subs = $pdo->query('SELECT subdomain FROM tenants ORDER BY subdomain')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['main', 's1', 's2'], $subs);
    }

    public function testImportMissingHonoursRelativeTenantsDirectory(): void {
        $projectRoot = dirname(__DIR__, 2);
        $base = sys_get_temp_dir() . '/tenants_rel_' . uniqid('', true);
        $tenantsDir = $base . '/relative';
        $tenantFolder = $tenantsDir . '/delta';

        mkdir($tenantFolder, 0777, true);

        $relative = $this->makeRelativePath($projectRoot, $tenantsDir);
        $previousEnv = getenv('TENANTS_DIR');
        $hadServer = array_key_exists('TENANTS_DIR', $_SERVER);
        $hadEnv = array_key_exists('TENANTS_DIR', $_ENV);
        $previousServer = $hadServer ? $_SERVER['TENANTS_DIR'] : null;
        $previousEnvEntry = $hadEnv ? $_ENV['TENANTS_DIR'] : null;

        putenv('TENANTS_DIR=' . $relative);
        $_SERVER['TENANTS_DIR'] = $relative;
        $_ENV['TENANTS_DIR'] = $relative;

        try {
            $pdo = new class extends PDO {
                public function __construct() {
                    parent::__construct('sqlite::memory:');
                }

                public function getAttribute($attr): mixed {
                    if ($attr === PDO::ATTR_DRIVER_NAME) {
                        return 'pgsql';
                    }

                    return parent::getAttribute($attr);
                }

                public function exec($statement): int|false {
                    if (preg_match('/^(CREATE|DROP) SCHEMA/i', $statement)) {
                        return 0;
                    }

                    return parent::exec($statement);
                }
            };
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
            $pdo->exec('CREATE TABLE tenants(' .
                'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
                'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
                'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
                'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
                'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
                'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)');
            $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
            $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('public')");

            $service = new TenantService($pdo);
            $result = $service->importMissing();

            $this->assertFalse($result['throttled']);
            $this->assertSame(2, $result['imported']);
            $subs = $pdo->query('SELECT subdomain FROM tenants ORDER BY subdomain')->fetchAll(PDO::FETCH_COLUMN);
            $this->assertContains('delta', $subs);
        } finally {
            if ($previousEnv === false) {
                putenv('TENANTS_DIR');
            } else {
                putenv('TENANTS_DIR=' . $previousEnv);
            }

            if ($hadServer) {
                $_SERVER['TENANTS_DIR'] = $previousServer;
            } else {
                unset($_SERVER['TENANTS_DIR']);
            }

            if ($hadEnv) {
                $_ENV['TENANTS_DIR'] = $previousEnvEntry;
            } else {
                unset($_ENV['TENANTS_DIR']);
            }

            if (is_dir($tenantFolder)) {
                rmdir($tenantFolder);
            }
            if (is_dir($tenantsDir)) {
                rmdir($tenantsDir);
            }
            if (is_dir($base)) {
                rmdir($base);
            }
        }
    }

    public function testImportMissingIsThrottledWithinCooldown(): void {
        $pdo = new class extends PDO {
            public function __construct() {
                parent::__construct('sqlite::memory:');
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
            'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
            'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
            'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
            'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
        $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('public')");
        $pdo->exec('CREATE TABLE settings(key TEXT PRIMARY KEY, value TEXT)');

        $service = new TenantService($pdo);
        $first = $service->importMissing();
        $this->assertSame(1, $first['imported']);
        $this->assertFalse($first['throttled']);

        $second = $service->importMissing();
        $this->assertSame(0, $second['imported']);
        $this->assertTrue($second['throttled']);
        $this->assertArrayHasKey('sync', $second);
        $this->assertTrue($second['sync']['is_throttled']);
    }

    public function testImportMissingSyncsTenantsDirectory(): void {
        $root = dirname(__DIR__, 2);
        $tenantsDir = $root . '/tenants';
        $sub = 't' . uniqid();
        $createdRoot = false;
        if (!is_dir($tenantsDir)) {
            mkdir($tenantsDir);
            $createdRoot = true;
        }
        mkdir($tenantsDir . '/' . $sub);

        $pdo = new class extends PDO {
            public function __construct() {
                parent::__construct('sqlite::memory:');
            }

            public function getAttribute($attr): mixed {
                if ($attr === PDO::ATTR_DRIVER_NAME) {
                    return 'pgsql';
                }
                return parent::getAttribute($attr);
            }

            public function exec($statement): int|false {
                if (str_starts_with($statement, 'CREATE SCHEMA')) {
                    if (preg_match('/CREATE SCHEMA "?([^" ]+)"?/i', $statement, $m)) {
                        parent::exec("INSERT INTO information_schema.schemata(schema_name) VALUES('{$m[1]}')");
                    }
                    return 0;
                }
                return parent::exec($statement);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE tenants(' .
            'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
            'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
            'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
            'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
            'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
            'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
        $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('public')");
        $service = new TenantService($pdo);
        $result = $service->importMissing();
        $this->assertSame(2, $result['imported']);
        $this->assertFalse($result['throttled']);
        $subs = $pdo->query('SELECT subdomain FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['main', $sub], $subs);
        $schemas = $pdo->query('SELECT schema_name FROM information_schema.schemata')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains($sub, $schemas);

        rmdir($tenantsDir . '/' . $sub);
        if ($createdRoot) {
            rmdir($tenantsDir);
        }
    }

    public function testImportMissingUsesRelativeTenantsDir(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $tempRoot = sys_get_temp_dir() . '/tenants_relative_' . uniqid('', true);
        $tenantsDir = $tempRoot . '/tenants';
        mkdir($tenantsDir, 0777, true);
        $subdomain = 'rel' . uniqid();
        mkdir($tenantsDir . '/' . $subdomain);

        $relativePath = $this->makeRelativePath($projectRoot, $tenantsDir);

        $previousEnv = getenv('TENANTS_DIR');
        $hadEnv = $previousEnv !== false;
        putenv('TENANTS_DIR=' . $relativePath);

        try {
            $pdo = new class extends PDO {
                public function __construct() {
                    parent::__construct('sqlite::memory:');
                }

                public function getAttribute($attr): mixed
                {
                    if ($attr === PDO::ATTR_DRIVER_NAME) {
                        return 'pgsql';
                    }

                    return parent::getAttribute($attr);
                }

                public function exec($statement): int|false
                {
                    if (str_starts_with($statement, 'CREATE SCHEMA')) {
                        return 0;
                    }

                    return parent::exec($statement);
                }
            };
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
            $pdo->exec('CREATE TABLE tenants(' .
                'uid TEXT PRIMARY KEY, subdomain TEXT, plan TEXT, billing_info TEXT, stripe_customer_id TEXT, ' .
                'stripe_subscription_id TEXT, stripe_price_id TEXT, stripe_status TEXT, ' .
                'stripe_current_period_end TEXT, stripe_cancel_at_period_end INTEGER, ' .
                'imprint_name TEXT, imprint_street TEXT, imprint_zip TEXT, imprint_city TEXT, ' .
                'imprint_email TEXT, custom_limits TEXT, onboarding_state TEXT NOT NULL DEFAULT "pending", ' .
                'plan_started_at TEXT, plan_expires_at TEXT, created_at TEXT)');
            $pdo->exec('CREATE TABLE information_schema.schemata(schema_name TEXT)');
            $pdo->exec("INSERT INTO information_schema.schemata(schema_name) VALUES('public')");

            $service = new TenantService($pdo);
            $result = $service->importMissing();

            $this->assertSame(2, $result['imported']);
            $this->assertFalse($result['throttled']);

            $subs = $pdo->query('SELECT subdomain FROM tenants ORDER BY subdomain')->fetchAll(PDO::FETCH_COLUMN);
            $this->assertSame(['main', $subdomain], $subs);
        } finally {
            if ($hadEnv) {
                putenv('TENANTS_DIR=' . $previousEnv);
            } else {
                putenv('TENANTS_DIR');
            }

            if (is_dir($tenantsDir . '/' . $subdomain)) {
                rmdir($tenantsDir . '/' . $subdomain);
            }
            if (is_dir($tenantsDir)) {
                rmdir($tenantsDir);
            }
            if (is_dir($tempRoot)) {
                rmdir($tempRoot);
            }
        }
    }
}
