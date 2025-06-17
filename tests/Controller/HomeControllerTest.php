<?php

declare(strict_types=1);

namespace Tests\Controller;

use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    public function testHomePage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function withCompetitionMode(callable $fn): void
    {
        $cfgPath = dirname(__DIR__, 2) . '/data/config.json';
        $orig = file_get_contents($cfgPath);
        $cfg = json_decode($orig, true);
        $cfg['competitionMode'] = true;
        file_put_contents($cfgPath, json_encode($cfg, JSON_PRETTY_PRINT) . "\n");
        try {
            $fn();
        } finally {
            file_put_contents($cfgPath, $orig);
        }
    }

    public function testCompetitionRedirect(): void
    {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);
            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals(['/help'], $response->getHeader('Location'));
        });
    }

    public function testCompetitionAllowsCatalog(): void
    {
        $this->withCompetitionMode(function () {
            $app = $this->getAppInstance();
            $request = $this->createRequest('GET', '/?katalog=station_1');
            $response = $app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });
    }
}
