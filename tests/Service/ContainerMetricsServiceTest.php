<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ContainerMetricsService;
use PHPUnit\Framework\TestCase;

class ContainerMetricsServiceTest extends TestCase
{
    public function testMissingCgroupPathIsUnavailable(): void
    {
        $service = new ContainerMetricsService('/non-existent/path', 0);
        $result = $service->read();

        self::assertArrayHasKey('timestamp', $result);
        self::assertFalse($result['available']);
        self::assertSame('cgroup path not available', $result['message']);
    }

    public function testReadsV2MetricsFromFilesystem(): void
    {
        $base = $this->createTempDir('cgroup-v2-');

        file_put_contents($base . '/cgroup.controllers', "cpu memory\n");
        file_put_contents($base . '/memory.current', "1024\n");
        file_put_contents($base . '/memory.max', "2048\n");
        file_put_contents($base . '/cpu.stat', "usage_usec 1000000\n");
        file_put_contents($base . '/memory.events', "oom 1\noom_kill 2\n");

        $service = new ContainerMetricsService($base, 1000);
        $result = $service->read();

        self::assertTrue($result['available']);
        self::assertSame(2, $result['cgroupVersion']);
        self::assertSame(1024, $result['memory']['currentBytes']);
        self::assertSame(2048, $result['memory']['limitBytes']);
        self::assertSame(50.0, $result['memory']['usagePercent']);
        self::assertSame(1, $result['oomEvents']['oom']);
        self::assertSame(2, $result['oomEvents']['oomKill']);
        self::assertSame(0.0, $result['cpu']['usagePercent']);
    }

    private function createTempDir(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(5));
        if (!mkdir($path) && !is_dir($path)) {
            $this->fail('Failed to create temp directory');
        }

        $this->addCleanup($path);

        return $path;
    }

    private function addCleanup(string $path): void
    {
        $this->addToAssertionCount(0);
        register_shutdown_function(static function () use ($path): void {
            if (!is_dir($path)) {
                return;
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileInfo) {
                if ($fileInfo->isDir()) {
                    @rmdir($fileInfo->getPathname());
                } else {
                    @unlink($fileInfo->getPathname());
                }
            }
            @rmdir($path);
        });
    }
}
