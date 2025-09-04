<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\CatalogService;
use App\Service\ConfigService;
use App\Service\TenantService;
use PDO;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                comment TEXT,
                design_path TEXT,
                event_uid TEXT
            );
            CREATE UNIQUE INDEX catalogs_unique_sort_order ON catalogs(event_uid, sort_order);
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        return $pdo;
    }

    private function createPdoNoComment(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                design_path TEXT,
                event_uid TEXT
            );
            CREATE UNIQUE INDEX catalogs_unique_sort_order ON catalogs(event_uid, sort_order);
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        return $pdo;
    }

    private function createPdoNoOptionalColumns(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT);');
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE catalogs(
                uid TEXT PRIMARY KEY,
                sort_order INTEGER NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                file TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                raetsel_buchstabe TEXT,
                event_uid TEXT
            );
            CREATE UNIQUE INDEX catalogs_unique_sort_order ON catalogs(event_uid, sort_order);
            SQL
        );
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE questions(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalog_uid TEXT NOT NULL,
                sort_order INTEGER,
                type TEXT NOT NULL,
                prompt TEXT NOT NULL,
                options TEXT,
                answers TEXT,
                terms TEXT,
                items TEXT,
                cards TEXT,
                right_label TEXT,
                left_label TEXT,
                UNIQUE(catalog_uid, sort_order)
            );
            SQL
        );
        $pdo->exec(
            'CREATE TABLE tenants('
            . 'uid TEXT, '
            . 'subdomain TEXT, '
            . 'plan TEXT, '
            . 'custom_limits TEXT, '
            . 'plan_started_at TEXT, '
            . 'plan_expires_at TEXT, '
            . 'stripe_customer_id TEXT, '
            . 'stripe_subscription_id TEXT, '
            . 'stripe_price_id TEXT, '
            . 'stripe_status TEXT, '
            . 'stripe_current_period_end TEXT, '
            . 'stripe_cancel_at_period_end INTEGER'
            . ');'
        );
        return $pdo;
    }

    public function testReadWrite(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'test.json';
        $catalog = [[
            'uid' => 'uid1',
            'sort_order' => 'cat1',
            'slug' => 'cat1',
            'file' => $file,
            'name' => 'Test',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $data = [[
            'type' => 'swipe',
            'prompt' => 'Hello',
            'cards' => [['text' => 'A', 'correct' => true]],
            'rightLabel' => 'Yes',
            'leftLabel' => 'No',
        ]];

        $service->write($file, $data);
        $this->assertJsonStringEqualsJsonString(
            json_encode($data, JSON_PRETTY_PRINT),
            $service->read($file)
        );
    }

    public function testWriteWithoutCommentColumn(): void
    {
        $pdo = $this->createPdoNoComment();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid4',
            'sort_order' => 'nc',
            'slug' => 'nc',
            'file' => 'nc.json',
            'name' => 'NC',
            'comment' => 'ignored',
        ]];
        $service->write('catalogs.json', $catalog);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('ignored', $rows[0]['comment']);
    }

    public function testWriteWithoutOptionalColumns(): void
    {
        $pdo = $this->createPdoNoOptionalColumns();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid9',
            'sort_order' => 'no',
            'slug' => 'no',
            'file' => 'no.json',
            'name' => 'NoCols',
            'comment' => 'c',
            'design_path' => 'd.svg',
        ]];
        $service->write('catalogs.json', $catalog);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('c', $rows[0]['comment']);
        $this->assertSame('d.svg', $rows[0]['design_path']);
    }

    public function testReadReturnsNullIfMissing(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);

        $this->assertNull($service->read('missing.json'));
    }

    public function testDelete(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'del.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid2',
            'sort_order' => 'del',
            'slug' => 'del',
            'file' => $file,
            'name' => 'Del',
            'comment' => ''
        ]]);
        $service->write($file, []);
        $stmt = $pdo->query('SELECT COUNT(*) FROM questions');
        $this->assertSame(0, (int)$stmt->fetchColumn());
        $service->delete($file);
        $stmt = $pdo->query('SELECT COUNT(*) FROM catalogs');
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testDeleteQuestion(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $file = 'q.json';
        $service->write('catalogs.json', [[
            'uid' => 'uid3',
            'sort_order' => 'qid',
            'slug' => 'qid',
            'file' => $file,
            'name' => 'Q',
            'comment' => ''
        ]]);
        $data = [
            ['type' => 'text', 'prompt' => 'A'],
            ['type' => 'text', 'prompt' => 'B'],
        ];
        $service->write($file, $data);

        $service->deleteQuestion($file, 0);
        $remaining = json_decode($service->read($file), true);
        $this->assertCount(2, $remaining);
    }

    public function testSlugChangeDoesNotDeleteQuestions(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $service->write('catalogs.json', [[
            'uid' => 'uid8',
            'sort_order' => 1,
            'slug' => 'old',
            'file' => 'old.json',
            'name' => 'Old',
            'comment' => '',
        ]]);
        $questions = [['type' => 'text', 'prompt' => 'Q1']];
        $service->write('old.json', $questions);

        $service->write('catalogs.json', [[
            'uid' => 'uid8',
            'sort_order' => 1,
            'slug' => 'new',
            'file' => 'old.json',
            'name' => 'New',
            'comment' => '',
        ]]);

        $stmt = $pdo->query("SELECT COUNT(*) FROM questions WHERE catalog_uid='uid8'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
        $this->assertSame(
            $questions,
            json_decode((string) $service->read('old.json'), true)
        );
    }

    public function testReorderCatalogs(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $initial = [
            ['uid' => 'u1', 'sort_order' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u1', 'sort_order' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'A', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'B', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $list = json_decode($service->read('catalogs.json'), true);
        $this->assertSame('b', $list[0]['slug']);
        $this->assertSame('a', $list[1]['slug']);
    }

    public function testNamesRemainAfterReorder(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $initial = [
            ['uid' => 'u1', 'sort_order' => 1, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u2', 'sort_order' => 2, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u3', 'sort_order' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $initial);

        $reordered = [
            ['uid' => 'u2', 'sort_order' => 1, 'slug' => 'b', 'file' => 'b.json', 'name' => 'Two', 'comment' => ''],
            ['uid' => 'u1', 'sort_order' => 2, 'slug' => 'a', 'file' => 'a.json', 'name' => 'One', 'comment' => ''],
            ['uid' => 'u3', 'sort_order' => 3, 'slug' => 'c', 'file' => 'c.json', 'name' => 'Three', 'comment' => ''],
        ];
        $service->write('catalogs.json', $reordered);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame(['Two', 'One', 'Three'], array_column($rows, 'name'));
    }

    public function testWriteWithoutActiveEventUid(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO config(event_uid) VALUES(NULL)");
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('ev1');
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid5',
            'sort_order' => 1,
            'slug' => 'ne',
            'file' => 'ne.json',
            'name' => 'NoEvent',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $stmt = $pdo->query('SELECT event_uid FROM catalogs');
        $this->assertSame('ev1', (string)$stmt->fetchColumn());
    }

    public function testWriteAcceptsIdField(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $service = new CatalogService($pdo, $cfg);
        $catalog = [[
            'uid' => 'uid6',
            'id' => 4,
            'slug' => 'foo',
            'file' => 'foo.json',
            'name' => 'Foo',
            'comment' => ''
        ]];
        $service->write('catalogs.json', $catalog);
        $rows = json_decode($service->read('catalogs.json'), true);
        $this->assertSame(4, $rows[0]['sort_order']);
    }

    public function testSaveAllRespectsCatalogLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $catalogs = [];
        for ($i = 1; $i <= 6; $i++) {
            $catalogs[] = [
                'uid' => 'u' . $i,
                'sort_order' => $i,
                'slug' => 'c' . $i,
                'file' => 'c' . $i . '.json',
                'name' => 'C' . $i,
                'comment' => ''
            ];
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-catalogs-exceeded');

        $service->write('catalogs.json', $catalogs);
    }

    public function testSaveAllRespectsStandardCatalogLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','standard')");
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $catalogs = [];
        for ($i = 1; $i <= 11; $i++) {
            $catalogs[] = [
                'uid' => 'u' . $i,
                'sort_order' => $i,
                'slug' => 'c' . $i,
                'file' => 'c' . $i . '.json',
                'name' => 'C' . $i,
                'comment' => ''
            ];
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-catalogs-exceeded');

        $service->write('catalogs.json', $catalogs);
    }

    public function testWriteRespectsQuestionLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','starter')");
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $questions = [];
        for ($i = 0; $i < 6; $i++) {
            $questions[] = ['type' => 'text', 'prompt' => 'Q' . $i];
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-questions-exceeded');

        $service->write('c1.json', $questions);
    }

    public function testWriteRespectsStandardQuestionLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec("INSERT INTO tenants(uid, subdomain, plan) VALUES('t1','sub1','standard')");
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $questions = [];
        for ($i = 0; $i < 11; $i++) {
            $questions[] = ['type' => 'text', 'prompt' => 'Q' . $i];
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-questions-exceeded');

        $service->write('c1.json', $questions);
    }

    public function testCustomLimitOverridesCatalogLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, custom_limits) " .
            "VALUES('t1','sub1','starter','{\"maxCatalogsPerEvent\":6}')"
        );
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $catalogs = [];
        for ($i = 1; $i <= 6; $i++) {
            $catalogs[] = [
                'uid' => 'u' . $i,
                'sort_order' => $i,
                'slug' => 'c' . $i,
                'file' => 'c' . $i . '.json',
                'name' => 'C' . $i,
                'comment' => ''
            ];
        }
        $service->write('catalogs.json', $catalogs);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-catalogs-exceeded');
        $catalogs[] = [
            'uid' => 'u7',
            'sort_order' => 7,
            'slug' => 'c7',
            'file' => 'c7.json',
            'name' => 'C7',
            'comment' => ''
        ];
        $service->write('catalogs.json', $catalogs);
    }

    public function testCustomLimitOverridesQuestionLimit(): void
    {
        $pdo = $this->createPdo();
        $cfg = new ConfigService($pdo);
        $cfg->setActiveEventUid('e1');
        $pdo->exec(
            "INSERT INTO tenants(uid, subdomain, plan, custom_limits) " .
            "VALUES('t1','sub1','starter','{\"maxQuestionsPerCatalog\":6}')"
        );
        $tenantSvc = new TenantService($pdo);
        $service = new CatalogService($pdo, $cfg, $tenantSvc, 'sub1');

        $questions = [];
        for ($i = 0; $i < 6; $i++) {
            $questions[] = ['type' => 'text', 'prompt' => 'Q' . $i];
        }
        $service->write('c1.json', $questions);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('max-questions-exceeded');
        $questions[] = ['type' => 'text', 'prompt' => 'Q6'];
        $service->write('c1.json', $questions);
    }
}
