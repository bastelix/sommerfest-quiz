<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    private function setupDb(): string
    {
        $db = tempnam(sys_get_temp_dir(), 'db');
        putenv('POSTGRES_DSN=sqlite:' . $db);
        putenv('POSTGRES_USER=');
        putenv('POSTGRES_PASSWORD=');
        $_ENV['POSTGRES_DSN'] = 'sqlite:' . $db;
        $_ENV['POSTGRES_USER'] = '';
        $_ENV['POSTGRES_PASSWORD'] = '';
        return $db;
    }

    public function testHomePage(): void
    {
        $db = $this->setupDb();
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        unlink($db);
    }

    private function withCompetitionMode(callable $fn): void
    {
        $cfgPath = dirname(__DIR__, 2) . '/data/config.json';
        $orig = file_get_contents($cfgPath);
        $cfg = json_decode($orig, true);
        $cfg['competitionMode'] = true;
        file_put_contents($cfgPath, json_encode($cfg, JSON_PRETTY_PRINT) . "\n");

        // Ensure a fresh database and seed a catalog entry for the tests
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        $pdo->exec("INSERT INTO events(uid,name) VALUES('1','Event')");
        $pdo->exec(
            "INSERT INTO catalogs(uid,sort_order,slug,file,name,event_uid) " .
            "VALUES('c1',1,'station_1','station_1.json','Station 1','1')"
        );

        try {
            $fn();
        } finally {
            file_put_contents($cfgPath, $orig);
            unlink($db);
        }
    }

    public function testCompetitionRedirect(): void
    {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });
    }

    public function testCompetitionAllowsCatalog(): void
    {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/')->withQueryParams(['katalog' => 'station_1']);
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });
    }

    public function testEventsAsHomePage(): void
    {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'events']);
        $pdo->exec("INSERT INTO events(uid,name) VALUES('1','Event')");

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

    public function testLandingAsHomePage(): void
    {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'landing']);

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

    public function testLandingSkippedWithCatalogLink(): void
    {
        $db = $this->setupDb();
        $this->getAppInstance();
        $pdo = \App\Infrastructure\Database::connectFromEnv();
        \App\Infrastructure\Migrations\Migrator::migrate($pdo, dirname(__DIR__, 2) . '/migrations');
        (new \App\Service\SettingsService($pdo))->save(['home_page' => 'landing']);
        $pdo->exec("INSERT INTO events(uid,name) VALUES('1','Event')");
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
}
