<?php

declare(strict_types=1);

namespace Tests;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

use App\Infrastructure\Database;
use App\Infrastructure\Migrations\Migrator;
use App\Infrastructure\Migrations\MigrationScriptRunner;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RunMigrationsScriptTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::setFactory(null);
        Database::setConnectHook(null);
        Migrator::setHook(null);
        parent::tearDown();
    }

    public function testScriptPinsBaseConnectionToPublicSchema(): void
    {
        $schemas = [];
        $migratorCalls = [];
        $basePdo = new SpyPDO();

        $connections = [$basePdo];
        $factoryCalls = 0;
        Database::setFactory(static function () use (&$connections, &$factoryCalls) {
            $factoryCalls++;
            if ($connections === []) {
                throw new RuntimeException('Unexpected database connection request.');
            }

            return array_shift($connections);
        });

        Database::setConnectHook(static function (string $schema, PDO $pdo) use (&$schemas): void {
            $schemas[] = [
                'schema' => $schema,
                'pdo' => spl_object_id($pdo),
            ];
        });

        Migrator::setHook(static function (PDO $pdo, string $dir) use (&$migratorCalls): bool {
            $migratorCalls[] = [
                'pdo' => spl_object_id($pdo),
                'dir' => $dir,
            ];

            return false;
        });

        $originalEnv = $this->captureEnv(['POSTGRES_DSN', 'POSTGRES_USER', 'POSTGRES_PASSWORD']);

        try {
            $dsn = 'pgsql:host=localhost;options=--search_path=main';
            $this->setEnv('POSTGRES_DSN', $dsn);
            $this->setEnv('POSTGRES_USER', 'test-user');
            $this->setEnv('POSTGRES_PASSWORD', 'test-pass');

            $errors = MigrationScriptRunner::run(dirname(__DIR__) . '/migrations');
        } finally {
            $this->restoreEnv($originalEnv);
        }

        $this->assertSame(1, $factoryCalls, 'Expected exactly one base connection.');
        $this->assertNotEmpty($schemas, 'Expected at least one schema-bound connection.');
        $this->assertSame('public', $schemas[0]['schema']);
        $this->assertSame(spl_object_id($basePdo), $schemas[0]['pdo']);

        $hookCountsByConnection = [];
        foreach ($schemas as $schemaCall) {
            $pdoId = $schemaCall['pdo'];
            $hookCountsByConnection[$pdoId] = ($hookCountsByConnection[$pdoId] ?? 0) + 1;
        }

        foreach ($hookCountsByConnection as $pdoId => $count) {
            $this->assertSame(
                1,
                $count,
                sprintf('Expected hook to run once for connection %d, but ran %d times.', $pdoId, $count)
            );
        }

        $this->assertNotEmpty($migratorCalls, 'Migrator should be invoked for the base connection.');
        $this->assertSame(spl_object_id($basePdo), $migratorCalls[0]['pdo']);
        $this->assertContains('SELECT subdomain FROM tenants', $basePdo->queries);
        $this->assertSame([], $errors, 'Tenant migrations should not report errors.');
    }

    /**
     * @param array<string|null> $keys
     * @return array<string, string|null>
     */
    private function captureEnv(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = getenv($key);
            $values[$key] = $value === false ? null : $value;
        }

        return $values;
    }

    /**
     * @param array<string, string|null> $values
     */
    private function restoreEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

class SpyPDO extends PDO
{
    /** @var list<string> */
    public array $queries = [];
    /** @var list<string> */
    public array $execStatements = [];
    /** @var list<string> */
    private array $tenantRows;

    /**
     * @param list<string> $tenantRows
     */
    public function __construct(array $tenantRows = [])
    {
        parent::__construct('sqlite::memory:');
        $this->tenantRows = $tenantRows;
    }

    public function exec(string $statement): int|false
    {
        $this->execStatements[] = $statement;

        return 0;
    }

    public function query(string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[] = $statement;

        if ($statement === 'SELECT subdomain FROM tenants') {
            $rows = array_map(static fn (string $value): array => [$value], $this->tenantRows);

            return FakeStatement::create($rows);
        }

        return FakeStatement::create([]);
    }

    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'pgsql';
        }

        return null;
    }

    public function prepare(string $statement, array $driverOptions = []): PDOStatement|false
    {
        return FakeStatement::create([]);
    }

    /**
     * @param list<string> $rows
     */
    public function setTenantRows(array $rows): void
    {
        $this->tenantRows = $rows;
    }
}

class FakeStatement extends PDOStatement
{
    /** @var array<int, array<int, mixed>> */
    private array $rows;

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    public static function create(array $rows): self
    {
        return new self($rows);
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
