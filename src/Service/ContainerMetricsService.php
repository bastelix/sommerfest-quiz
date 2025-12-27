<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;

class ContainerMetricsService
{
    private string $cgroupPath;

    private int $cpuSampleMicros;

    public function __construct(string $cgroupPath = '/sys/fs/cgroup', int $cpuSampleMicros = 200000)
    {
        $this->cgroupPath = rtrim($cgroupPath, '/');
        $this->cpuSampleMicros = max(0, $cpuSampleMicros);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $timestamp = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);

        if (!is_dir($this->cgroupPath)) {
            return [
                'available' => false,
                'timestamp' => $timestamp,
                'message' => 'cgroup path not available',
            ];
        }

        if ($this->isCgroupV2()) {
            return $this->readV2($timestamp);
        }

        return $this->readV1($timestamp);
    }

    private function isCgroupV2(): bool
    {
        return is_file($this->cgroupPath . '/cgroup.controllers');
    }

    /**
     * @return array<string, mixed>
     */
    private function readV2(string $timestamp): array
    {
        $memoryCurrentFile = $this->cgroupPath . '/memory.current';
        $memoryMaxFile = $this->cgroupPath . '/memory.max';
        $cpuStatFile = $this->cgroupPath . '/cpu.stat';
        $oomEventsFile = $this->cgroupPath . '/memory.events';

        if (!is_file($memoryCurrentFile) || !is_file($cpuStatFile)) {
            return [
                'available' => false,
                'timestamp' => $timestamp,
                'cgroupVersion' => 2,
                'message' => 'required cgroup v2 files missing',
            ];
        }

        $memoryCurrent = $this->readIntFromFile($memoryCurrentFile);
        $memoryMaxRaw = $this->readStringFromFile($memoryMaxFile);
        $memoryLimit = $memoryMaxRaw === 'max' ? null : $this->toInt($memoryMaxRaw);
        $cpuPercent = $this->sampleCpuPercent(fn (): ?int => $this->readCpuUsageMicros($cpuStatFile), 1);
        $oomStats = $this->readOomEventsV2($oomEventsFile);

        return [
            'available' => $memoryCurrent !== null,
            'timestamp' => $timestamp,
            'cgroupVersion' => 2,
            'memory' => [
                'currentBytes' => $memoryCurrent,
                'limitBytes' => $memoryLimit,
                'usagePercent' => $this->computeMemoryPercent($memoryCurrent, $memoryLimit),
            ],
            'cpu' => [
                'usagePercent' => $cpuPercent,
                'sampleMicros' => $this->cpuSampleMicros,
            ],
            'oomEvents' => $oomStats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readV1(string $timestamp): array
    {
        $memoryCurrentFile = $this->cgroupPath . '/memory/memory.usage_in_bytes';
        $memoryLimitFile = $this->cgroupPath . '/memory/memory.limit_in_bytes';
        $cpuUsageFile = $this->cgroupPath . '/cpu/cpuacct.usage';
        $oomControlFile = $this->cgroupPath . '/memory/memory.oom_control';

        if (!is_file($memoryCurrentFile) || !is_file($cpuUsageFile)) {
            return [
                'available' => false,
                'timestamp' => $timestamp,
                'cgroupVersion' => 1,
                'message' => 'required cgroup v1 files missing',
            ];
        }

        $memoryCurrent = $this->readIntFromFile($memoryCurrentFile);
        $memoryLimit = $this->readIntFromFile($memoryLimitFile);
        $cpuPercent = $this->sampleCpuPercent(fn (): ?int => $this->readIntFromFile($cpuUsageFile), 1000);
        $oomStats = $this->readOomEventsV1($oomControlFile);

        return [
            'available' => $memoryCurrent !== null,
            'timestamp' => $timestamp,
            'cgroupVersion' => 1,
            'memory' => [
                'currentBytes' => $memoryCurrent,
                'limitBytes' => $memoryLimit,
                'usagePercent' => $this->computeMemoryPercent($memoryCurrent, $memoryLimit),
            ],
            'cpu' => [
                'usagePercent' => $cpuPercent,
                'sampleMicros' => $this->cpuSampleMicros,
            ],
            'oomEvents' => $oomStats,
        ];
    }

    private function readIntFromFile(string $path): ?int
    {
        $value = $this->readStringFromFile($path);
        return $this->toInt($value);
    }

    private function readStringFromFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return trim($content);
    }

    private function toInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function readCpuUsageMicros(string $statFile): ?int
    {
        if (!is_file($statFile)) {
            return null;
        }

        $lines = file($statFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[0] === 'usage_usec' && is_numeric($parts[1])) {
                return (int) $parts[1];
            }
        }

        return null;
    }

    private function sampleCpuPercent(callable $reader, int $usageScale): ?float
    {
        $usageScale = max(1, $usageScale);
        $start = $reader();
        if ($start === null) {
            return null;
        }

        if ($this->cpuSampleMicros > 0) {
            usleep($this->cpuSampleMicros);
        }

        $end = $reader();
        if ($end === null || $end < $start) {
            return null;
        }

        $elapsed = $this->cpuSampleMicros;
        if ($elapsed <= 0) {
            return null;
        }

        $delta = $end - $start;
        $percent = ($delta / ($elapsed * $usageScale)) * 100;

        return round($percent, 2);
    }

    private function computeMemoryPercent(?int $current, ?int $limit): ?float
    {
        if ($current === null || $limit === null || $limit <= 0) {
            return null;
        }

        $percent = ($current / $limit) * 100;

        return round(min(100, max(0, $percent)), 2);
    }

    /**
     * @return array<string, int>
     */
    private function readOomEventsV2(string $eventsFile): array
    {
        $oom = 0;
        $oomKill = 0;

        if (!is_file($eventsFile)) {
            return ['oom' => $oom, 'oomKill' => $oomKill];
        }

        $lines = file($eventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['oom' => $oom, 'oomKill' => $oomKill];
        }

        foreach ($lines as $line) {
            [$key, $value] = array_pad(preg_split('/\s+/', trim($line)), 2, null);
            if ($key === 'oom' && is_numeric($value)) {
                $oom = (int) $value;
            }
            if ($key === 'oom_kill' && is_numeric($value)) {
                $oomKill = (int) $value;
            }
        }

        return ['oom' => $oom, 'oomKill' => $oomKill];
    }

    /**
     * @return array<string, int>
     */
    private function readOomEventsV1(string $controlFile): array
    {
        $oomKill = 0;
        if (!is_file($controlFile)) {
            return ['oom' => 0, 'oomKill' => $oomKill];
        }

        $lines = file($controlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['oom' => 0, 'oomKill' => $oomKill];
        }

        foreach ($lines as $line) {
            [$key, $value] = array_pad(preg_split('/\s+/', trim($line)), 2, null);
            if ($key === 'oom_kill' && is_numeric($value)) {
                $oomKill = (int) $value;
            }
        }

        return ['oom' => 0, 'oomKill' => $oomKill];
    }
}
