<?php

declare(strict_types=1);

namespace Tests\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    private function setupDb(): string {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('MAIN_DOMAIN=main.test');
        $_ENV['MAIN_DOMAIN'] = 'main.test';
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'text/html'],
        ?array $cookies = null,
        array $serverParams = []
    ): Request {
        $request = parent::createRequest($method, $path, $headers, $cookies, $serverParams);
        return $request->withUri($request->getUri()->withHost('main.test'));
    }

    public function testHomePage(): void {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        unlink($db);
    }

    public function testHomePageAllowsHeadRequest(): void {
        $db = $this->setupDb();

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('HEAD', '/');
            $response = $app->handle($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertSame('', (string) $response->getBody());
        } finally {
            unlink($db);
        }
    }

    public function testEventCatalogOverview(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'station_1','station_1.json','Station 1','1')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['event' => 'event']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Station 1', $body);
            $this->assertStringContainsString('Event', $body);
        } finally {
            unlink($db);
        }
    }

    private function withCompetitionMode(callable $fn): void {
        // Ensure a fresh database and seed a catalog entry for the tests
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'station_1','station_1.json','Station 1','1')"
        );

        $config = new \App\Service\ConfigService($pdo);
        $config->saveConfig(['event_uid' => '1', 'competitionMode' => true]);

        try {
            $fn();
        } finally {
            unlink($db);
        }
    }

    public function testCompetitionRedirect(): void {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['event' => 'event']);
            $response = $app->handle($request);
            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals('/help', $response->getHeaderLine('Location'));
        });
    }

    public function testCompetitionAllowsCatalog(): void {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams([
                'event' => 'event',
                'katalog' => 'station_1',
            ]);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });
    }

    public function testCompetitionAllowsCatalogSlugCaseInsensitive(): void {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams([
                'event' => 'event',
                'katalog' => 'STATION_1',
            ]);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });
    }

    public function testEventsAsHomePage(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'events']);
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('Veranstaltungen', (string)$response->getBody());
        } finally {
            unlink($db);
        }
    }

    public function testEventsStartpageUsesCustomPageWhenAvailable(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'events']);
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO pages(slug,title,content) VALUES('events','Events','<p>Custom content</p>')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Custom content', $body);
            $this->assertStringNotContainsString('Veranstaltungen', $body);
        } finally {
            unlink($db);
        }
    }

    public function testLandingAsHomePage(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'landing']);
        $pdo->exec(
            "INSERT INTO pages(slug,title,content) VALUES('landing','Landing','Trete gegen Freunde und Kollegen an')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('Trete gegen Freunde und Kollegen an', (string)$response->getBody());
        } finally {
            unlink($db);
        }
    }

    public function testLandingSkippedWithCatalogLink(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'landing']);
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'station_1','station_1.json','Station 1','1')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['katalog' => 'station_1']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Station 1', $body);
            $this->assertStringNotContainsString('Trete gegen Freunde und Kollegen an', $body);
        } finally {
            unlink($db);
        }
    }

    public function testLandingShownWithoutParamsDespiteSessionCatalog(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'landing']);
        $pdo->exec(
            "INSERT INTO pages(slug,title,content) VALUES('landing','Landing','Trete gegen Freunde und Kollegen an')"
        );
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('1','event','Event')");
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'station_1','station_1.json','Station 1','1')"
        );

        session_start();
        $_SESSION['catalog_slug'] = 'station_1';
        $_SESSION['event_uid'] = '1';

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Trete gegen Freunde und Kollegen an', $body);
            $this->assertStringNotContainsString('Station 1', $body);
        } finally {
            unlink($db);
        }
    }

    public function testUpcomingEventShowsMarketingPage(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');

        $start = (new \DateTimeImmutable('+1 day'))->format('Y-m-d\\TH:i');
        $end = (new \DateTimeImmutable('+2 days'))->format('Y-m-d\\TH:i');
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,start_date,end_date) " .
            "VALUES('future','event-future','Zukünftiges Event','$start','$end')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['event' => 'event-future']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Unser Event startet am', $body);
            $this->assertStringContainsString('Jetzt informieren', $body);
        } finally {
            unlink($db);
        }
    }

    public function testFinishedEventShowsMarketingPage(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');

        $start = (new \DateTimeImmutable('-2 days'))->format('Y-m-d\\TH:i');
        $end = (new \DateTimeImmutable('-1 day'))->format('Y-m-d\\TH:i');
        $pdo->exec(
            "INSERT INTO events(uid,slug,name,start_date,end_date) " .
            "VALUES('past','event-past','Vergangenes Event','$start','$end')"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['event' => 'event-past']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $body = (string) $response->getBody();
            $this->assertStringContainsString('Das Event ist beendet', $body);
            $this->assertStringContainsString('team@quizrace.app', $body);
        } finally {
            unlink($db);
        }
    }

    public function testCalserverMaintenanceDomainStartPage(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec(
            "INSERT INTO pages(slug,title,content) VALUES(" .
            "'calserver-maintenance','calHelp Wartung','<p>Wartungshinweis</p>')"
        );
        $pdo->exec("UPDATE settings SET value = 'calserver-maintenance' WHERE key = 'home_page'");

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('Wartungshinweis', (string) $response->getBody());
        } finally {
            unlink($db);
        }
    }

    public function testCustomDomainWithoutStartpageShowsMaintenancePage(): void
    {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');

        $domainService = new \App\Service\DomainService($pdo);
        $domainService->createDomain('tenant.test', namespace: 'tenant-a');

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $request = $request->withUri($request->getUri()->withHost('tenant.test'));
            $response = $app->handle($request);

            $this->assertEquals(200, $response->getStatusCode());
            $body = strtolower((string) $response->getBody());
            $this->assertStringContainsString('wir richten diese seite gerade ein', $body);
            $this->assertStringContainsString('tenant.test', $body);
        } finally {
            unlink($db);
        }
    }

    public function testCustomStartpageOverridesReservedSlug(): void
    {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');

        $pdo->exec('DELETE FROM pages');
        $pdo->exec(
            "INSERT INTO pages(namespace, slug, title, content, language, is_startpage) "
            . "VALUES('default','help','Custom Help','<p>Individuelle Hilfe</p>','de',1)"
        );

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('Individuelle Hilfe', (string) $response->getBody());
        } finally {
            unlink($db);
        }
    }

    public function testHomePageWithSlug(): void {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $uid = str_repeat('b', 32);
        $pdo->exec("INSERT INTO events(uid,slug,name) VALUES('$uid','sluggy','Event')");

        try {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['event' => 'sluggy']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        } finally {
            unlink($db);
        }
    }
}
