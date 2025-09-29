<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Process\Process;

/**
 * Run a shell script in the background.
 * Falls back to exec if the Symfony Process component is unavailable.
 */
function runBackgroundProcess(string $script, array $args = []): void {
    $cmd = array_merge([$script], $args);

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/onboarding.log';
    $escapedCommand = implode(' ', array_map('escapeshellarg', $cmd));
    $commandLine = $escapedCommand . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
    file_put_contents(
        $logFile,
        '[' . date('c') . '] ' . $escapedCommand . PHP_EOL,
        FILE_APPEND
    );

    try {
        if (!class_exists(Process::class)) {
            throw new \RuntimeException('Symfony Process component is unavailable.');
        }

        $process = Process::fromShellCommandline($commandLine);
        $process->start();
        return;
    } catch (\Throwable $e) {
        error_log('runBackgroundProcess failed: ' . $e->getMessage());
        exec($commandLine . ' &');
    }
}

/**
 * Run a shell script synchronously and capture its output.
 * Falls back to exec if the Symfony Process component is unavailable.
 *
 * @param string $script The script to run
 * @param array $args Arguments passed to the script
 * @param bool $throwOnError Throw an exception on failure instead of returning the error output
 *
 * @return array{success: bool, stdout: string, stderr: string}
 */
function runSyncProcess(string $script, array $args = [], bool $throwOnError = false): array {
    $cmd = array_merge([$script], $args);

    try {
        if (!class_exists(Process::class)) {
            throw new \RuntimeException('Symfony Process component is unavailable.');
        }

        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run();

        $success = $process->isSuccessful();
        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        if (!$success) {
            $message = $stderr !== '' ? $stderr : $stdout;
            error_log('runSyncProcess failed: ' . $message);
            if ($throwOnError) {
                throw new \RuntimeException($message);
            }
        }

        return ['success' => $success, 'stdout' => $stdout, 'stderr' => $stderr];
    } catch (\Throwable $e) {
        error_log('runSyncProcess failed: ' . $e->getMessage());

        $command = escapeshellarg($script);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        exec($command . ' 2>&1', $output, $exitCode);
        $outputString = implode("\n", $output);
        $success = $exitCode === 0;

        if (!$success) {
            error_log('runSyncProcess exec fallback failed: ' . $outputString);
            if ($throwOnError) {
                throw new \RuntimeException($outputString, 0, $e);
            }
        }

        return [
            'success' => $success,
            'stdout' => $success ? $outputString : '',
            'stderr' => $success ? '' : $outputString,
        ];
    }
}
