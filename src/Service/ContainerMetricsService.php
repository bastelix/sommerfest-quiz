<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use RuntimeException;

class ContainerMetricsService
{
    private string $cgroupRoot;

    /**
     * @var callable
     */
    private $clock;

    /**
     * @var array{version:int, usage:int, timestamp:float}|null
     */
    private static ?array $lastCpuSample = null;

    public function __construct(string $cgroupRoot = '/sys/fs/cgroup', ?callable $clock = null)
    {
        $this->cgroupRoot = rtrim($cgroupRoot, '/');
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /**
     * @return array{
     *   timestamp: string,
     *   cgroupVersion: int,
     *   memory: array{currentBytes:int,maxBytes:int|null},
     *   cpu: array{usageMicros:int|null,usageNanos:int|null,percent:float|null,sampleWindowSeconds:float|null},
     *   oom: array{events:int|null,kills:int|null}
     * }
     */
    public function collect(): array
    {
        if (!is_dir($this->cgroupRoot)) {
            throw new RuntimeException('cgroup root unavailable');
        }

        $version = $this->detectCgroupVersion();

        $timestamp = (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        if ($version === 2) {
            $memoryCurrent = $this->readInt($this->cgroupRoot . '/memory.current');
            $memoryMax = $this->readMaxValue($this->cgroupRoot . '/memory.max');
            [$cpuUsage, $cpuPercent, $sampleSeconds] = $this->computeCpuPercent(
                $this->readInt($this->cgroupRoot . '/cpu.stat', 'usage_usec'),
                $version
            );
            [$oomEvents, $oomKills] = $this->readOomEventsV2();

            return [
                'timestamp' => $timestamp,
                'cgroupVersion' => 2,
                'memory' => ['currentBytes' => $memoryCurrent, 'maxBytes' => $memoryMax],
                'cpu' => [
                    'usageMicros' => $cpuUsage,
                    'usageNanos' => null,
                    'percent' => $cpuPercent,
                    'sampleWindowSeconds' => $sampleSeconds,
                ],
                'oom' => ['events' => $oomEvents, 'kills' => $oomKills],
            ];
        }

        $memoryCurrent = $this->readInt($this->cgroupRoot . '/memory/memory.usage_in_bytes');
        $memoryMax = $this->readInt($this->cgroupRoot . '/memory/memory.limit_in_bytes');
        [$cpuUsage, $cpuPercent, $sampleSeconds] = $this->computeCpuPercent(
            $this->readInt($this->cgroupRoot . '/cpu/cpuacct.usage'),
            1
        );
        [$oomEvents, $oomKills] = $this->readOomEventsV1();

        return [
            'timestamp' => $timestamp,
            'cgroupVersion' => 1,
            'memory' => ['currentBytes' => $memoryCurrent, 'maxBytes' => $memoryMax],
            'cpu' => [
                'usageMicros' => null,
                'usageNanos' => $cpuUsage,
                'percent' => $cpuPercent,
                'sampleWindowSeconds' => $sampleSeconds,
            ],
            'oom' => ['events' => $oomEvents, 'kills' => $oomKills],
        ];
    }

    private function detectCgroupVersion(): int
    {
        if (is_file($this->cgroupRoot . '/cgroup.controllers')) {
            return 2;
        }

        return 1;
    }

    private function readInt(string $path, ?string $key = null): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('Required metrics file missing: ' . $path);
        }

        $contents = trim((string) file_get_contents($path));
        if ($contents === '') {
            return 0;
        }

        if ($key !== null) {
            foreach (preg_split('/\r?\n/', $contents) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$k, $v] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, null);
                if ($k === $key) {
                    return (int) ($v ?? 0);
                }
            }

            return 0;
        }

        return (int) $contents;
    }

    private function readMaxValue(string $path): ?int
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));
        if ($contents === '' || $contents === 'max') {
            return null;
        }

        return (int) $contents;
    }

    /**
     * @return array{0:int|null,1:float|null,2:float|null}
     */
    private function computeCpuPercent(int $usage, int $version): array
    {
        $now = (float) ($this->clock)();
        $last = self::$lastCpuSample;
        self::$lastCpuSample = ['version' => $version, 'usage' => $usage, 'timestamp' => $now];

        if ($last === null || $last['version'] !== $version) {
            return [$usage, null, null];
        }

        $deltaUsage = $usage - $last['usage'];
        $deltaSeconds = max(0.0, $now - $last['timestamp']);
        if ($deltaUsage < 0 || $deltaSeconds <= 0.0) {
            return [$usage, null, $deltaSeconds];
        }

        $divisor = $version === 2 ? 1_000_000.0 : 1_000_000_000.0;
        $percent = ($deltaUsage / ($deltaSeconds * $divisor)) * 100.0;

        return [$usage, round($percent, 2), $deltaSeconds];
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function readOomEventsV2(): array
    {
        $eventsFile = $this->cgroupRoot . '/memory.events';
        if (!is_file($eventsFile)) {
            return [null, null];
        }

        $oom = null;
        $oomKill = null;
        foreach (preg_split('/\r?\n/', (string) file_get_contents($eventsFile)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $value] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, null);
            if ($name === 'oom') {
                $oom = (int) ($value ?? 0);
            } elseif ($name === 'oom_kill') {
                $oomKill = (int) ($value ?? 0);
            }
        }

        return [$oom, $oomKill];
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function readOomEventsV1(): array
    {
        $oomControl = $this->cgroupRoot . '/memory/memory.oom_control';
        if (!is_file($oomControl)) {
            return [null, null];
        }

        $oomKill = null;
        foreach (preg_split('/\r?\n/', (string) file_get_contents($oomControl)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $value] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, null);
            if ($name === 'oom_kill') {
                $oomKill = (int) ($value ?? 0);
            }
        }

        return [null, $oomKill];
    }
}
