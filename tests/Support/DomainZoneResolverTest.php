<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DomainZoneResolver;
use PHPUnit\Framework\TestCase;

final class DomainZoneResolverTest extends TestCase
{
    public function testDerivesRegistrableZoneFromHost(): void
    {
        $resolver = new DomainZoneResolver();

        self::assertSame('calserver.com', $resolver->deriveZone('calserver.com'));
        self::assertSame('calserver.com', $resolver->deriveZone('www.calserver.com'));
        self::assertSame('green-test.de', $resolver->deriveZone('future.green-test.de'));
        self::assertSame('customer-a.de', $resolver->deriveZone('landing.customer-a.de'));
    }
}
