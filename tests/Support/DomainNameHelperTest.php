<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DomainNameHelper;
use PHPUnit\Framework\TestCase;

class DomainNameHelperTest extends TestCase
{
    /**
     * @dataProvider provideDomains
     *
     * @param non-empty-string $expected
     */
    public function testNormalizeStripsKnownPrefixes(string $input, string $expected): void {
        self::assertSame($expected, DomainNameHelper::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDomains(): iterable {
        yield 'base domain' => ['example.com', 'example.com'];
        yield 'www prefix' => ['www.example.com', 'example.com'];
        yield 'admin prefix' => ['admin.example.com', 'example.com'];
        yield 'assistant prefix' => ['assistant.example.com', 'example.com'];
        yield 'mixed case with scheme' => ['HTTPS://ASSISTANT.Example.COM/path', 'example.com'];
    }

    /**
     * @dataProvider provideLegacyDomains
     */
    public function testNormalizeKeepsPrefixesWhenAdminStrippingDisabled(string $input, string $expected): void {
        self::assertSame($expected, DomainNameHelper::normalize($input, stripAdmin: false));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLegacyDomains(): iterable {
        yield 'www removed' => ['www.example.com', 'example.com'];
        yield 'admin kept' => ['admin.example.com', 'admin.example.com'];
        yield 'assistant kept' => ['assistant.example.com', 'assistant.example.com'];
    }
}
