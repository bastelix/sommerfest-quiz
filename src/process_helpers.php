<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Process\Process;

/**
 * Run a shell script in the background.
 * Falls back to exec if the Symfony Process component is unavailable.
 */
function runBackgroundProcess(string $script, array $args = []): void
{
    $cmd = array_merge([$script], $args);

    if (class_exists(Process::class)) {
        $process = new Process($cmd);
        $process->disableOutput();
        $process->start();
        return;
    }

    $command = escapeshellcmd($script);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
    exec($command . ' > /dev/null 2>&1 &');
}

/**
 * Run a shell script synchronously and return success.
 * Falls back to exec if the Symfony Process component is unavailable.
 */
function runSyncProcess(string $script, array $args = []): bool
{
    $cmd = array_merge([$script], $args);

    if (class_exists(Process::class)) {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->disableOutput();
        $process->run();
        return $process->isSuccessful();
    }

    $command = escapeshellcmd($script);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
    exec($command, $output, $exitCode);
    return $exitCode === 0;
}

