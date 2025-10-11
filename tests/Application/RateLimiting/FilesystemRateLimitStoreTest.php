<?php

declare(strict_types=1);

namespace Tests\Application\RateLimiting;

use App\Application\RateLimiting\FilesystemRateLimitStore;
use PHPUnit\Framework\TestCase;

class FilesystemRateLimitStoreTest extends TestCase
{
    public function testIncrementGracefullyHandlesWriteFailures(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit_collision_' . uniqid('', true);
        if (file_put_contents($dir, 'marker') === false) {
            $this->fail('Unable to prepare filesystem collision for the test.');
        }

        $store = new FilesystemRateLimitStore($dir);

        $first = $store->increment('login', 60);
        $second = $store->increment('login', 60);

        self::assertSame(1, $first);
        self::assertSame(1, $second);
        self::assertFalse(is_file($dir . DIRECTORY_SEPARATOR . 'rlm_login.json'));

        unlink($dir);
    }
}
