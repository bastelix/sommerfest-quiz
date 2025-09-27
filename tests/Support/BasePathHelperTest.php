<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\BasePathHelper;
use PHPUnit\Framework\TestCase;

class BasePathHelperTest extends TestCase
{
    /**
     * @dataProvider provideBasePaths
     */
    public function testNormalize(?string $input, string $expected): void
    {
        self::assertSame($expected, BasePathHelper::normalize($input));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function provideBasePaths(): iterable
    {
        yield 'null' => [null, ''];
        yield 'empty string' => ['', ''];
        yield 'single slash' => ['/', ''];
        yield 'already normalized rootless' => ['sub', '/sub'];
        yield 'leading slash kept' => ['/admin', '/admin'];
        yield 'trailing slash trimmed' => ['/admin/', '/admin'];
        yield 'whitespace trimmed' => ['  /app ', '/app'];
        yield 'double slashes collapsed' => ['//nested//path//', '/nested/path'];
    }
}
