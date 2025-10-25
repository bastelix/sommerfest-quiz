<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TeamNameController;
use App\Service\ConfigService;
use App\Service\TeamNameService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class TeamNameControllerTest extends TestCase
{
    public function testReserveBatchReturnsJsonPayload(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-batch')
            ->willReturn([]);
        $config->expects(self::once())
            ->method('getConfig')
            ->willReturn([]);

        $service->expects(self::once())
            ->method('reserveBatchWithBuffer')
            ->with('ev-batch', 3, [], [], 0, null, 'ai')
            ->willReturn([
                [
                    'name' => 'Alpha Nebel',
                    'token' => 'tok-1',
                    'expires_at' => '2025-01-01T12:00:00Z',
                    'lexicon_version' => 2,
                    'total' => 120,
                    'remaining' => 118,
                    'fallback' => false,
                ],
                [
                    'name' => 'Beta Licht',
                    'token' => 'tok-2',
                    'expires_at' => '2025-01-01T12:00:00Z',
                    'lexicon_version' => 2,
                    'total' => 120,
                    'remaining' => 117,
                    'fallback' => false,
                ],
            ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/batch')
            ->withQueryParams(['event_uid' => 'ev-batch', 'count' => '3']);

        $response = $controller->reserveBatch($request, new Response());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-batch', $payload['event_id']);
        self::assertCount(2, $payload['reservations']);
        self::assertSame('Alpha Nebel', $payload['reservations'][0]['name']);
        self::assertSame(118, $payload['reservations'][0]['remaining']);
    }

    public function testReserveBatchAppliesConfiguredFilters(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-filters')
            ->willReturn([
                'randomNameDomains' => ['nature', 'science'],
                'randomNameTones' => ['playful'],
                'randomNameLocale' => 'de-DE',
                'randomNameStrategy' => 'lexicon',
            ]);
        $config->expects(self::never())
            ->method('getConfig');

        $service->expects(self::once())
            ->method('reserveBatchWithBuffer')
            ->with('ev-filters', 10, ['nature', 'science'], ['playful'], 0, 'de-DE', 'lexicon')
            ->willReturn([
                [
                    'name' => 'Nebelwelle',
                    'token' => 'tok-3',
                    'expires_at' => '2025-01-01T12:00:00Z',
                    'lexicon_version' => 2,
                    'total' => 80,
                    'remaining' => 75,
                    'fallback' => false,
                ],
            ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/batch')
            ->withQueryParams(['event_uid' => 'ev-filters', 'count' => '25']);

        $response = $controller->reserveBatch($request, new Response());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-filters', $payload['event_id']);
        self::assertCount(1, $payload['reservations']);
        self::assertSame('Nebelwelle', $payload['reservations'][0]['name']);
        self::assertSame(2, $payload['reservations'][0]['lexicon_version']);
        self::assertSame(75, $payload['reservations'][0]['remaining']);
    }
}
