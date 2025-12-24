<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Controller\TeamNameController;
use App\Service\ConfigService;
use App\Service\TeamNameService;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class TeamNameControllerTest extends TestCase
{
    public function testReserveAppliesConfiguredBufferAndStrategy(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-lex')
            ->willReturn([
                'randomNameDomains' => ['nature', 'science'],
                'randomNameTones' => ['playful'],
                'randomNameBuffer' => 5,
                'randomNameLocale' => 'fr-CA',
                'randomNameStrategy' => 'lexicon',
            ]);
        $config->expects(self::never())->method('getConfig');

        $service->expects(self::once())
            ->method('reserveWithBuffer')
            ->with('ev-lex', ['nature', 'science'], ['playful'], 5, 'fr-CA', 'lexicon')
            ->willReturn([
                'name' => 'BetaLicht',
                'token' => 'tok-lex',
                'expires_at' => '2025-02-01T12:00:00Z',
                'lexicon_version' => 3,
                'total' => 42,
                'remaining' => 41,
                'fallback' => false,
            ]);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names');
        $request->getBody()->write(json_encode(['event_uid' => 'ev-lex'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->reserve($request, new Response());

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-lex', $payload['event_id']);
        self::assertSame('BetaLicht', $payload['name']);
        self::assertSame('tok-lex', $payload['token']);
        self::assertFalse($payload['fallback']);
    }

    public function testReserveUsesGlobalFallbackWhenEventConfigEmpty(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-empty')
            ->willReturn([]);
        $config->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'randomNameDomains' => ['science', ''],
                'randomNameTones' => ['bold', null],
                'randomNameBuffer' => '-4',
                'randomNameLocale' => '  es-MX ',
                'randomNameStrategy' => 'unknown',
            ]);

        $service->expects(self::once())
            ->method('reserveWithBuffer')
            ->with('ev-empty', ['science', ''], ['bold', null], 0, 'es-MX', 'ai')
            ->willReturn([
                'name' => 'Photonenfreunde',
                'token' => 'tok-empty',
                'expires_at' => '2025-02-01T12:00:00Z',
                'lexicon_version' => 3,
                'total' => 12,
                'remaining' => 10,
                'fallback' => false,
            ]);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names');
        $request->getBody()->write(json_encode(['event_id' => 'ev-empty'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->reserve($request, new Response());

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('tok-empty', $payload['token']);
        self::assertSame('ev-empty', $payload['event_id']);
        self::assertSame('Photonenfreunde', $payload['name']);
    }

    public function testReserveReturnsBadRequestWhenEventIdMissing(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::never())
            ->method('getConfigForEvent');
        $config->expects(self::once())
            ->method('getConfig')
            ->willReturn([]);
        $service->expects(self::never())->method('reserveWithBuffer');

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names');
        $request->getBody()->write(json_encode([], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->reserve($request, new Response());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testReserveReturnsBadRequestWhenServiceThrows(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-error')
            ->willReturn([]);
        $config->expects(self::once())
            ->method('getConfig')
            ->willReturn([]);

        $service->expects(self::once())
            ->method('reserveWithBuffer')
            ->willThrowException(new InvalidArgumentException('invalid'));

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names');
        $request->getBody()->write(json_encode(['event_uid' => 'ev-error'], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->reserve($request, new Response());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

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
                    'name' => 'AlphaNebel',
                    'token' => 'tok-1',
                    'expires_at' => '2025-01-01T12:00:00Z',
                    'lexicon_version' => 2,
                    'total' => 120,
                    'remaining' => 118,
                    'fallback' => false,
                ],
                [
                    'name' => 'BetaLicht',
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
        self::assertSame('AlphaNebel', $payload['reservations'][0]['name']);
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

    public function testReserveBatchReturnsBadRequestWhenServiceThrows(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-bad-batch')
            ->willReturn([]);
        $config->expects(self::once())
            ->method('getConfig')
            ->willReturn([]);

        $service->expects(self::once())
            ->method('reserveBatchWithBuffer')
            ->willThrowException(new PDOException('failure'));

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/batch')
            ->withQueryParams(['event_uid' => 'ev-bad-batch', 'count' => '5']);

        $response = $controller->reserveBatch($request, new Response());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testStatusReportsDiagnostics(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $config->expects(self::once())
            ->method('getConfigForEvent')
            ->with('ev-status')
            ->willReturn([
                'randomNameDomains' => [' sport ', 'kultur', ''],
                'randomNameTones' => ['lustig', 'seriös'],
                'randomNameBuffer' => 150,
                'randomNameLocale' => 'de-DE',
                'randomNameStrategy' => 'ai',
            ]);
        $config->expects(self::never())
            ->method('getConfig');

        $service->expects(self::once())
            ->method('getAiDiagnostics')
            ->willReturn([
                'enabled' => true,
                'available' => false,
                'last_attempt_at' => '2024-05-01T10:00:00Z',
                'last_success_at' => '2024-05-01T09:50:00Z',
                'last_error' => 'Timeout contacting AI service.',
                'client_last_error' => 'Timeout contacting AI service.',
                'last_response_at' => '2024-05-01T10:00:00Z',
            ]);
        $service->expects(self::once())
            ->method('getAiCacheState')
            ->with('ev-status')
            ->willReturn([
                'total' => 7,
                'entries' => [],
            ]);
        $service->expects(self::once())
            ->method('getLexiconInventory')
            ->with('ev-status', ['sport', 'kultur'], ['lustig', 'seriös'])
            ->willReturn([
                'total' => 500,
                'reserved' => 120,
                'available' => 380,
            ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/status')
            ->withQueryParams(['event_uid' => 'ev-status']);

        $response = $controller->status($request, new Response());

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-status', $payload['event_id']);
        self::assertSame('ai', $payload['strategy']);
        self::assertSame(150, $payload['buffer']);
        self::assertSame('de-DE', $payload['locale']);
        self::assertSame(['sport', 'kultur'], $payload['domains']);
        self::assertSame(['lustig', 'seriös'], $payload['tones']);
        self::assertSame('Timeout contacting AI service.', $payload['ai']['last_error']);
        self::assertTrue($payload['ai']['required_for_event']);
        self::assertFalse($payload['ai']['active_for_event']);
        self::assertSame(7, $payload['ai']['cache']['total']);
        self::assertSame(500, $payload['lexicon']['total']);
        self::assertSame(120, $payload['lexicon']['reserved']);
        self::assertSame(380, $payload['lexicon']['available']);
    }

    public function testPreviewFillsCacheAndReturnsLog(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $service->expects(self::once())
            ->method('warmUpAiSuggestionsWithLog')
            ->with('ev-preview', ['nature', 'science'], ['playful'], 'de-DE', 6)
            ->willReturn([
                'cache' => [
                    'total' => 2,
                    'entries' => [
                        [
                            'cache_key' => 'cache-hash',
                            'available' => 2,
                            'names' => ['Solar Echo', 'Quantum Owls'],
                            'filters' => [
                                'domains' => ['nature', 'science'],
                                'tones' => ['playful'],
                                'locale' => 'de-DE',
                            ],
                        ],
                    ],
                ],
                'log' => [
                    'context' => 'warmup',
                    'meta' => [],
                    'entries' => [
                        ['code' => 'target', 'level' => 'info', 'context' => ['count' => 6]],
                        ['code' => 'status', 'level' => 'success', 'context' => ['status' => 'completed', 'count' => 2]],
                    ],
                    'status' => 'completed',
                    'error' => null,
                ],
            ]);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names/preview');
        $request->getBody()->write(json_encode([
            'event_id' => 'ev-preview',
            'domains' => ['nature', 'science'],
            'tones' => ['playful'],
            'locale' => 'de-DE',
            'count' => 6,
        ], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->preview($request, new Response());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-preview', $payload['event_id']);
        self::assertSame(['nature', 'science'], $payload['filters']['domains']);
        self::assertSame(['playful'], $payload['filters']['tones']);
        self::assertSame('de-DE', $payload['filters']['locale']);
        self::assertSame(6, $payload['filters']['count']);
        self::assertSame(2, $payload['cache']['total']);
        self::assertCount(1, $payload['cache']['entries']);
        self::assertSame(['Solar Echo', 'Quantum Owls'], $payload['cache']['entries'][0]['names']);
        self::assertArrayHasKey('log', $payload);
        self::assertSame('completed', $payload['log']['status']);
        self::assertCount(2, $payload['log']['entries']);
        self::assertArrayNotHasKey('suggestions', $payload);
    }

    public function testPreviewReturnsBadRequestWithoutEvent(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $service->expects(self::never())->method('previewAiSuggestions');
        $service->expects(self::never())->method('getAiCacheState');

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/team-names/preview');
        $request->getBody()->write(json_encode(['domains' => ['nature']], JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();

        $response = $controller->preview($request, new Response());

        self::assertSame(400, $response->getStatusCode());
    }

    public function testHistoryReturnsEntriesFromService(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $service->expects(self::once())
            ->method('listNamesForEvent')
            ->with('ev-history', 120)
            ->willReturn([
                [
                    'id' => 5,
                    'name' => 'Team Freigegeben',
                    'status' => 'released',
                    'fallback' => false,
                    'reservation_token' => 'tok-1',
                    'reserved_at' => '2024-05-01T10:00:00+00:00',
                    'assigned_at' => '2024-05-01T10:10:00+00:00',
                    'released_at' => '2024-05-01T10:20:00+00:00',
                ],
            ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/history')
            ->withQueryParams(['event_uid' => 'ev-history', 'limit' => '120']);

        $response = $controller->history($request, new Response());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ev-history', $payload['event_id']);
        self::assertSame(120, $payload['limit']);
        self::assertCount(1, $payload['entries']);
        self::assertSame('Team Freigegeben', $payload['entries'][0]['name']);
        self::assertSame('released', $payload['entries'][0]['status']);
    }

    public function testHistoryReturnsBadRequestWithoutEvent(): void
    {
        $service = $this->createMock(TeamNameService::class);
        $config = $this->createMock(ConfigService::class);
        $controller = new TeamNameController($service, $config);

        $service->expects(self::never())->method('listNamesForEvent');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/team-names/history');

        $response = $controller->history($request, new Response());

        self::assertSame(400, $response->getStatusCode());
    }
}
