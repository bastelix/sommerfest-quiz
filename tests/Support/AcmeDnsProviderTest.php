<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\AcmeDnsProvider;
use InvalidArgumentException;
use Tests\TestCase;

final class AcmeDnsProviderTest extends TestCase
{
    public function testNormalizesAliasesAndDefaults(): void
    {
        $this->assertSame('dns_hetzner', AcmeDnsProvider::normalize(null));
        $this->assertSame('dns_hetzner', AcmeDnsProvider::normalize(' hetzner '));
        $this->assertSame('dns_cf', AcmeDnsProvider::normalize('CLOUDflare'));
    }

    public function testNormalizesUnicodeWhitespaceAndControlCharacters(): void
    {
        $nonBreakingSpace = "\u{00A0}";

        $this->assertSame('dns_hetzner', AcmeDnsProvider::normalize($nonBreakingSpace . 'Hetzner'));
        $this->assertSame('dns_cf', AcmeDnsProvider::normalize("\tCloudFlare\n"));
        $this->assertSame('dns_cf', AcmeDnsProvider::normalize("CF\t\r\n"));
    }

    public function testRejectsUnsupportedProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcmeDnsProvider::normalize('dns_example');
    }

    public function testSharedDefaultUsedForQueueingAndProvisioning(): void
    {
        $original = getenv('ACME_WILDCARD_PROVIDER');
        putenv('ACME_WILDCARD_PROVIDER');

        try {
            $queueDefault = AcmeDnsProvider::fromEnv();

            $this->assertSame(
                AcmeDnsProvider::DEFAULT_PROVIDER,
                $queueDefault,
                'Queueing falls back to the shared default provider when env is empty.'
            );
            $this->assertSame(
                AcmeDnsProvider::DEFAULT_PROVIDER,
                AcmeDnsProvider::normalize(''),
                'Provisioning script uses the same default through AcmeDnsProvider::normalize().'
            );
        } finally {
            if ($original === false) {
                putenv('ACME_WILDCARD_PROVIDER');
            } else {
                putenv('ACME_WILDCARD_PROVIDER=' . $original);
            }
        }
    }
}
