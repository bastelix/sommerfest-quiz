<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\DomainStartPageService;
use App\Service\PageService;
use PDO;
use PHPUnit\Framework\TestCase;

class DomainStartPageServiceTest extends TestCase
{
    public function testMarketingAliasSlugsAreExcludedFromOptions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE pages ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'slug TEXT NOT NULL, '
            . 'title TEXT NOT NULL, '
            . 'content TEXT NOT NULL)'
        );

        $pdo->exec("INSERT INTO pages (slug, title, content) VALUES ('calserver-en', 'A English Calserver', '...')");
        $pdo->exec("INSERT INTO pages (slug, title, content) VALUES ('calserver', 'Calserver', '...')");
        $pdo->exec("INSERT INTO pages (slug, title, content) VALUES ('calserver-maintenance-en', 'A Maintenance EN', '...')");
        $pdo->exec("INSERT INTO pages (slug, title, content) VALUES ('calserver-maintenance', 'Maintenance', '...')");
        $pdo->exec("INSERT INTO pages (slug, title, content) VALUES ('special', 'Special Offer', '...')");

        $service = new DomainStartPageService($pdo);
        $pageService = new PageService($pdo);

        $options = $service->getStartPageOptions($pageService);

        $this->assertArrayHasKey('help', $options);
        $this->assertArrayHasKey('events', $options);
        $this->assertArrayHasKey('calserver', $options);
        $this->assertArrayHasKey('calserver-maintenance', $options);
        $this->assertArrayHasKey('special', $options);
        $this->assertArrayNotHasKey('calserver-en', $options);
        $this->assertArrayNotHasKey('calserver-maintenance-en', $options);

        $this->assertSame('Calserver', $options['calserver']);
        $this->assertSame('Maintenance', $options['calserver-maintenance']);
        $this->assertSame('Special Offer', $options['special']);
        $this->assertCount(5, $options);
    }

    /**
     * @dataProvider provideDomains
     */
    public function testNormalizeDomainSanitizesInput(string $input, string $expected, bool $stripAdmin): void
    {
        $service = new DomainStartPageService(new PDO('sqlite::memory:'));

        self::assertSame($expected, $service->normalizeDomain($input, $stripAdmin));
    }

    /**
     * @return iterable<string,array{string,string,bool}>
     */
    public static function provideDomains(): iterable
    {
        yield 'plain domain' => ['calserver.de', 'calserver.de', true];
        yield 'with scheme' => ['https://calserver.de', 'calserver.de', true];
        yield 'with uppercase and path' => ['HTTP://WWW.CALSERVER.DE/foo', 'calserver.de', true];
        yield 'admin subdomain stripped' => ['admin.calserver.de', 'calserver.de', true];
        yield 'assistant subdomain stripped' => ['assistant.calserver.de', 'calserver.de', true];
        yield 'marketing subdomain kept when admin stripping disabled' => ['admin.calserver.de', 'admin.calserver.de', false];
        yield 'assistant subdomain kept when admin stripping disabled' => ['assistant.calserver.de', 'assistant.calserver.de', false];
    }

    public function testDetermineDomainsCanonicalisesMarketingEntries(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $service = new DomainStartPageService($pdo);

        $previous = getenv('MARKETING_DOMAINS');
        putenv('MARKETING_DOMAINS=calserver.com');
        $_ENV['MARKETING_DOMAINS'] = 'calserver.com';

        try {
            $domains = $service->determineDomains(null, 'calserver.com', 'legacy.example.com');

            $this->assertTrue(
                in_array(['domain' => 'calserver.com', 'normalized' => 'calserver', 'type' => 'marketing'], $domains, true)
            );

            $legacyEntry = null;
            foreach ($domains as $entry) {
                if ($entry['normalized'] === 'legacy.example.com') {
                    $legacyEntry = $entry;
                }
            }

            $this->assertNotNull($legacyEntry);
            $this->assertSame('legacy.example.com', $legacyEntry['domain']);
            $this->assertSame('custom', $legacyEntry['type']);
        } finally {
            if ($previous === false) {
                putenv('MARKETING_DOMAINS');
                unset($_ENV['MARKETING_DOMAINS']);
            } else {
                putenv('MARKETING_DOMAINS=' . $previous);
                $_ENV['MARKETING_DOMAINS'] = $previous;
            }
        }
    }
}
