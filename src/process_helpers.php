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
 * Run a shell script synchronously and return result details.
 * Falls back to exec if the Symfony Process component is unavailable.
 *
 * @return array{success: bool, output: string, error: string}
 */
function runSyncProcess(string $script, array $args = []): array
{
    $cmd = array_merge([$script], $args);

    if (class_exists(Process::class)) {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run();
        $success = $process->isSuccessful();
        $output = $process->getOutput();
        $error = $process->getErrorOutput();
    } else {
        $command = escapeshellcmd($script);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        $outputLines = [];
        exec($command . ' 2>&1', $outputLines, $exitCode);
        $success = $exitCode === 0;
        $output = implode("\n", $outputLines);
        $error = $success ? '' : $output;
    }

    if (!$success) {
        error_log(sprintf('Process "%s" failed: %s', $script, $error));
    }

    return [
        'success' => $success,
        'output' => $output,
        'error' => $error,
    ];
}
