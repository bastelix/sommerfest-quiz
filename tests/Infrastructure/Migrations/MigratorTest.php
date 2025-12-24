<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Migrations;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

use App\Infrastructure\Migrations\Migrator;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testAddsQrrememberColumnAndMigratesLegacyValues(): void
    {
        $pdo = new TenantAwarePDO();
        $pdo->setCurrentSchema('tenant1');

        $pdo->exec('CREATE TABLE config (id INTEGER PRIMARY KEY, legacy_qrremember BOOLEAN)');
        $pdo->exec('CREATE TABLE events (uid TEXT)');
        $pdo->exec('CREATE TABLE active_event (event_uid TEXT)');
        $pdo->exec('INSERT INTO config (id, legacy_qrremember) VALUES (1, 1)');
        $pdo->registerInformationSchemaColumn('config', 'QRRemember');

        $dir = sys_get_temp_dir() . '/migrations_' . uniqid('', true);
        if (!mkdir($dir) && !is_dir($dir)) {
            self::fail('Failed to create temporary migrations directory.');
        }
        file_put_contents($dir . '/0000_empty.sql', "-- noop\n");

        try {
            Migrator::migrate($pdo, $dir);
        } finally {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                array_map('unlink', $files);
            }
            rmdir($dir);
        }

        $columns = $pdo->query("PRAGMA table_info('config')")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_map(static fn (array $row): string => (string) $row['name'], $columns);
        self::assertContains('qrremember', array_map('strtolower', $columnNames));

        $row = $pdo->query('SELECT qrremember FROM config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertNotFalse($row);
        self::assertSame(1, (int) $row['qrremember']);
    }

    public function testIgnoresDuplicateOnboardingStateColumn(): void
    {
        $pdo = new TenantAwarePDO();
        $pdo->setCurrentSchema('public');
        $pdo->exec('CREATE TABLE config (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE events (uid TEXT)');
        $pdo->exec('CREATE TABLE active_event (event_uid TEXT)');
        $pdo->exec("CREATE TABLE tenants (uid TEXT PRIMARY KEY, onboarding_state TEXT NOT NULL DEFAULT 'pending')");
        $pdo->registerInformationSchemaColumn('tenants', 'onboarding_state');
        $pdo->exec("INSERT INTO tenants (uid, onboarding_state) VALUES ('t-1', 'pending')");

        $dir = sys_get_temp_dir() . '/migrations_' . uniqid('', true);
        if (!mkdir($dir) && !is_dir($dir)) {
            self::fail('Failed to create temporary migrations directory.');
        }

        $migrationFile = $dir . '/20260926_add_onboarding_state_to_tenants.sql';
        $migrationSql = <<<'SQL'
ALTER TABLE tenants ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT 'pending';

UPDATE tenants
SET onboarding_state = 'completed';
SQL;
        file_put_contents($migrationFile, $migrationSql);

        try {
            Migrator::migrate($pdo, $dir);
        } finally {
            $files = glob($dir . '/*');
            if (is_array($files)) {
                array_map('unlink', $files);
            }
            rmdir($dir);
        }

        $state = $pdo->query("SELECT onboarding_state FROM tenants WHERE uid = 't-1'")->fetchColumn();
        self::assertSame('completed', $state);

        $versions = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('20260926_add_onboarding_state_to_tenants.sql', $versions);
    }
}

final class TenantAwarePDO extends PDO
{
    private string $currentSchema = 'public';

    /**
     * @var array<string, array<string, array<string, bool>>>
     */
    private array $informationSchema = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function setCurrentSchema(string $schema): void
    {
        $this->currentSchema = $schema;
    }

    public function registerInformationSchemaColumn(string $table, string $column, ?string $schema = null): void
    {
        $schema ??= $this->currentSchema;
        $this->informationSchema[$schema][$table][$column] = true;
    }

    public function removeInformationSchemaColumn(string $table, string $column, ?string $schema = null): void
    {
        $schema ??= $this->currentSchema;
        if (isset($this->informationSchema[$schema][$table][$column])) {
            unset($this->informationSchema[$schema][$table][$column]);
        }
        if (($this->informationSchema[$schema][$table] ?? []) === []) {
            unset($this->informationSchema[$schema][$table]);
        }
        if (($this->informationSchema[$schema] ?? []) === []) {
            unset($this->informationSchema[$schema]);
        }
    }

    public function getAttribute($attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'pgsql';
        }

        return parent::getAttribute($attribute);
    }

    public function exec($statement): int|false
    {
        $statements = array_filter(array_map('trim', explode(';', $statement)));
        $result = 0;

        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }

            $statementForExecution = $this->rewriteLegacyColumn($stmt);

            if (preg_match('/^ALTER TABLE\s+tenants\s+ADD COLUMN\s+onboarding_state/i', $stmt) === 1) {
                if ($this->hasColumn('tenants', 'onboarding_state')) {
                    throw new PDOException('column "onboarding_state" of relation "tenants" already exists', 42701);
                }

                parent::exec("ALTER TABLE tenants ADD COLUMN onboarding_state TEXT NOT NULL DEFAULT 'pending'");
                $this->registerInformationSchemaColumn('tenants', 'onboarding_state');
                continue;
            }

            if (preg_match('/^ALTER TABLE\s+config\s+ADD COLUMN IF NOT EXISTS\s+qrremember/i', $stmt) === 1) {
                if (!$this->hasColumn('config', 'qrremember')) {
                    parent::exec('ALTER TABLE config ADD COLUMN qrremember BOOLEAN DEFAULT FALSE');
                }
                $this->registerInformationSchemaColumn('config', 'qrremember');
                continue;
            }

            if (preg_match('/^ALTER TABLE\s+config\s+DROP COLUMN IF EXISTS\s+"?QRRemember"?/i', $stmt) === 1) {
                $this->removeInformationSchemaColumn('config', 'QRRemember');
                continue;
            }

            $result = parent::exec($statementForExecution);
        }

        return $result;
    }

    public function query(string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement));
        if (is_string($normalized) && str_contains($normalized, 'FROM information_schema.columns')) {
            $table = $this->extractInformationSchemaValue($normalized, 'table_name');
            $column = $this->extractInformationSchemaValue($normalized, 'column_name');

            if ($table !== null && $column !== null) {
                $exists = $this->hasInformationSchemaColumn($table, $column);

                return ArrayResultStatement::fromSingleColumn($exists ? 1 : 0);
            }
        }

        return parent::query($this->rewriteLegacyColumn($statement), $fetchMode, ...$fetchModeArgs);
    }

    private function extractInformationSchemaValue(string $statement, string $field): ?string
    {
        if (preg_match(sprintf("/%s\s*=\s*'?([a-zA-Z0-9_]+)'?/i", preg_quote($field, '/')), $statement, $matches) === 1) {
            return $matches[1];
        }

        if (str_contains($statement, sprintf('%s = current_schema()', $field))) {
            return $this->currentSchema;
        }

        return null;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = parent::query(sprintf("PRAGMA table_info('%s')", $table));
        if ($stmt === false) {
            return false;
        }

        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $info) {
            if ((string) $info['name'] === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasInformationSchemaColumn(string $table, string $column, ?string $schema = null): bool
    {
        $schema ??= $this->currentSchema;

        if (isset($this->informationSchema[$schema][$table][$column])) {
            return true;
        }

        return $this->hasColumn($table, $column);
    }

    private function rewriteLegacyColumn(string $statement): string
    {
        return str_replace('"QRRemember"', 'legacy_qrremember', $statement);
    }
}

final class ArrayResultStatement extends PDOStatement
{
    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function __construct(private array $rows)
    {
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    public static function fromSingleColumn(mixed $value): self
    {
        return new self([[ $value ]]);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? null;

        return $row[$column] ?? false;
    }

    public function fetchAll(int $mode = PDO::ATTR_DEFAULT_FETCH_MODE, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static fn (array $row): mixed => $row[0] ?? null, $this->rows);
        }

        return $this->rows;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }
}
