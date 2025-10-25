<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use App\Support\TokenCipher;
use PDO;
use Tests\TestCase;
use Throwable;

class ConfigServiceTest extends TestCase
{
    public function testReadWriteConfig(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                loginRequired INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                title TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
                CheckAnswerButton TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                random_name_domains TEXT DEFAULT '[]',
                random_name_tones TEXT DEFAULT '[]',
                random_name_buffer INTEGER DEFAULT 0,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY REFERENCES events(uid))');
        $pdo->exec("INSERT INTO events(uid) VALUES('ev1')");
        $service = new ConfigService($pdo);
        $data = ['event_uid' => 'ev1', 'pageTitle' => 'Demo', 'QRUser' => false, 'QRRemember' => true];

        $service->saveConfig($data);
        $json = $service->getJson();
        $this->assertNotNull($json);
        $jsonPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $jsonPayload['randomNameDomains']);
        $this->assertSame([], $jsonPayload['randomNameTones']);
        $this->assertSame(0, $jsonPayload['randomNameBuffer']);

        $cfg = $service->getConfig();
        $this->assertSame('Demo', $cfg['pageTitle']);
        $this->assertFalse($cfg['QRUser']);
        $this->assertTrue($cfg['QRRemember']);
        $this->assertSame([], $cfg['randomNameDomains']);
        $this->assertSame([], $cfg['randomNameTones']);
        $this->assertSame(0, $cfg['randomNameBuffer']);
    }

    public function testSaveConfigPersistsRandomNameFilters(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                loginRequired INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                title TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
                CheckAnswerButton TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                random_name_domains TEXT DEFAULT '[]',
                random_name_tones TEXT DEFAULT '[]',
                random_name_buffer INTEGER DEFAULT 0,
                event_uid TEXT PRIMARY KEY
            );
            SQL
        );
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec("INSERT INTO events(uid) VALUES('ev-random')");

        $service = new ConfigService($pdo);
        $service->saveConfig([
            'event_uid' => 'ev-random',
            'randomNames' => true,
            'randomNameDomains' => ['Nature', 'Science'],
            'randomNameTones' => ['Playful', 'Bold'],
            'randomNameBuffer' => 7,
        ]);

        $row = $pdo->query(
            "SELECT random_name_domains, random_name_tones, random_name_buffer FROM config WHERE event_uid = 'ev-random'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame(['nature', 'science'], json_decode((string) $row['random_name_domains'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['playful', 'bold'], json_decode((string) $row['random_name_tones'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(7, (int) $row['random_name_buffer']);

        $config = $service->getConfig();
        $this->assertSame(['nature', 'science'], $config['randomNameDomains']);
        $this->assertSame(['playful', 'bold'], $config['randomNameTones']);
        $this->assertSame(7, $config['randomNameBuffer']);
    }

    public function testDashboardConfigRoundTripsThroughSnakeCaseColumns(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                event_uid TEXT PRIMARY KEY,
                dashboard_modules TEXT,
                dashboard_theme TEXT,
                dashboard_refresh_interval INTEGER,
                dashboard_fixed_height TEXT,
                dashboard_share_enabled INTEGER,
                dashboard_sponsor_enabled INTEGER,
                dashboard_info_text TEXT,
                dashboard_media_embed TEXT,
                dashboard_visibility_start TEXT,
                dashboard_visibility_end TEXT,
                dashboard_share_token TEXT,
                dashboard_sponsor_token TEXT,
                colors TEXT
            );
            SQL
        );
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec("INSERT INTO events(uid) VALUES('dash-event')");

        $tokenCipher = new class extends TokenCipher {
            public function __construct()
            {
            }

            public function encrypt(?string $value): ?string
            {
                if ($value === null) {
                    return null;
                }
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return null;
                }

                return 'fixed-' . $trimmed;
            }

            public function decrypt(?string $payload): ?string
            {
                if ($payload === null) {
                    return null;
                }
                $trimmed = trim($payload);
                if ($trimmed === '') {
                    return null;
                }
                if (strncmp($trimmed, 'fixed-', 6) === 0) {
                    return substr($trimmed, 6);
                }

                return null;
            }
        };

        $service = new ConfigService($pdo, $tokenCipher);
        $modules = [
            [
                'id' => 'rankings',
                'enabled' => true,
                'layout' => 'wide',
                'options' => [
                    'limit' => 10,
                    'pageSize' => 5,
                    'sort' => 'points',
                    'title' => 'Live-Rankings',
                    'showPlacement' => true,
                ],
            ],
            [
                'id' => 'infoBanner',
                'enabled' => true,
                'layout' => 'full',
            ],
        ];
        $service->saveConfig([
            'event_uid' => 'dash-event',
            'dashboardModules' => $modules,
            'dashboardTheme' => 'dark',
            'dashboardRefreshInterval' => 45,
            'dashboardFixedHeight' => '1080px',
            'dashboardShareEnabled' => true,
            'dashboardSponsorEnabled' => false,
            'dashboardInfoText' => 'Welcome back!',
            'dashboardMediaEmbed' => '<iframe>media</iframe>',
            'dashboardVisibilityStart' => '2025-07-01T10:00:00Z',
            'dashboardVisibilityEnd' => '2025-07-01T12:00:00Z',
            'colors' => ['primary' => '#001122'],
        ]);

        $stored = $pdo->query(
            "SELECT dashboard_modules, dashboard_theme, dashboard_refresh_interval, dashboard_fixed_height, " .
            "dashboard_share_enabled, dashboard_sponsor_enabled, dashboard_visibility_start, dashboard_visibility_end " .
            "FROM config WHERE event_uid = 'dash-event'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($stored);
        $this->assertSame($modules, json_decode((string) $stored['dashboard_modules'], true));
        $this->assertSame('dark', $stored['dashboard_theme']);
        $this->assertSame(45, (int) $stored['dashboard_refresh_interval']);
        $this->assertSame('1080px', $stored['dashboard_fixed_height']);
        $this->assertSame(1, (int) $stored['dashboard_share_enabled']);
        $this->assertSame(0, (int) $stored['dashboard_sponsor_enabled']);
        $this->assertSame('2025-07-01T10:00:00Z', $stored['dashboard_visibility_start']);
        $this->assertSame('2025-07-01T12:00:00Z', $stored['dashboard_visibility_end']);

        $service->setDashboardToken('dash-event', 'public', 'public-token');
        $service->setDashboardToken('dash-event', 'sponsor', 'sponsor-token');

        $tokens = $service->getDashboardTokens('dash-event');
        $this->assertSame('public-token', $tokens['public']);
        $this->assertSame('sponsor-token', $tokens['sponsor']);

        $config = $service->getConfigForEvent('dash-event');
        $this->assertSame($modules, $config['dashboardModules']);
        $this->assertSame('dark', $config['dashboardTheme']);
        $this->assertSame(45, $config['dashboardRefreshInterval']);
        $this->assertSame('1080px', $config['dashboardFixedHeight']);
        $this->assertTrue($config['dashboardShareEnabled']);
        $this->assertFalse($config['dashboardSponsorEnabled']);
        $this->assertSame('Welcome back!', $config['dashboardInfoText']);
        $this->assertSame('<iframe>media</iframe>', $config['dashboardMediaEmbed']);
        $this->assertSame('2025-07-01T10:00:00Z', $config['dashboardVisibilityStart']);
        $this->assertSame('2025-07-01T12:00:00Z', $config['dashboardVisibilityEnd']);
        $this->assertSame('public-token', $config['dashboardShareToken']);
        $this->assertSame('sponsor-token', $config['dashboardSponsorToken']);
    }

    public function testGetConfigReturnsEmptyWithoutActiveEvent(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                pageTitle TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);
        $pdo->exec("INSERT INTO config(pageTitle,event_uid) VALUES('Demo','ev1')");

        $this->assertSame([], $service->getConfig());
    }

    public function testGetJsonReturnsNullIfEmpty(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                displayErrorDetails INTEGER,
                loginRequired INTEGER,
                QRRemember INTEGER,
                logoPath TEXT,
                title TEXT,
                backgroundColor TEXT,
                buttonColor TEXT,
                startTheme TEXT,
                CheckAnswerButton TEXT,
                QRRestrict INTEGER,
                randomNames INTEGER DEFAULT 1,
                competitionMode INTEGER,
                teamResults INTEGER,
                photoUpload INTEGER,
                puzzleWordEnabled INTEGER,
                puzzleWord TEXT,
                puzzleFeedback TEXT,
                inviteText TEXT,
                event_uid TEXT
            );
            SQL
        );
        $service = new ConfigService($pdo);

        $this->assertNull($service->getJson());
        $this->assertSame([], $service->getConfig());
    }

    public function testSetActiveEventUidRollsBackOnInsertFailure(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE config(event_uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY)');
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('foo')");
        $pdo->exec(
            "CREATE TRIGGER fail_insert BEFORE INSERT ON active_event " .
            "BEGIN SELECT RAISE(FAIL, 'no insert'); END;"
        );
        $service = new ConfigService($pdo);

        try {
            $service->setActiveEventUid('bar');
            $this->fail('Exception was not thrown');
        } catch (Throwable $e) {
            // expected
        }

        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('foo', $uid);
    }

    public function testSetActiveEventUidIgnoresUnknownEvent(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE events(uid TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE active_event(event_uid TEXT PRIMARY KEY REFERENCES events(uid))');
        $pdo->exec("INSERT INTO events(uid) VALUES('ev1')");
        $pdo->exec("INSERT INTO active_event(event_uid) VALUES('ev1')");
        $service = new ConfigService($pdo);

        $service->setActiveEventUid('ev2');

        $uid = $pdo->query('SELECT event_uid FROM active_event')->fetchColumn();
        $this->assertSame('ev1', $uid);
    }

    public function testSetActiveEventUidDoesNotInsertConfigForEmptyEvent(): void {
        $pdo = $this->createDatabase();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $service = new ConfigService($pdo);

        $service->setActiveEventUid('');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM config')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testVerifyDashboardTokenSupportsLegacyPlaintextValues(): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE config(
                event_uid TEXT PRIMARY KEY,
                dashboard_share_token TEXT,
                dashboard_sponsor_token TEXT
            )
            SQL
        );
        $publicToken = 'legacyPublicToken123456';
        $sponsorToken = 'legacySponsorToken654321';
        $pdo->exec(
            "INSERT INTO config(event_uid, dashboard_share_token, dashboard_sponsor_token) " .
            "VALUES('ev1', '" . $publicToken . "', '" . $sponsorToken . "')"
        );

        $service = new ConfigService($pdo, new TokenCipher('test-secret'));

        $tokens = $service->getDashboardTokens('ev1');
        $this->assertSame($publicToken, $tokens['public']);
        $this->assertSame($sponsorToken, $tokens['sponsor']);

        $config = $service->getConfigForEvent('ev1');
        $this->assertSame($publicToken, $config['dashboardShareToken']);
        $this->assertSame($sponsorToken, $config['dashboardSponsorToken']);

        $this->assertSame('public', $service->verifyDashboardToken('ev1', $publicToken));
        $this->assertSame('sponsor', $service->verifyDashboardToken('ev1', $sponsorToken));
    }
}
