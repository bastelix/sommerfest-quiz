<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Process\Process;

/**
 * Run a shell script in the background.
 * Falls back to exec if the Symfony Process component is unavailable.
 */
function runBackgroundProcess(string $script, array $args = [], ?string $logFile = null): void {
    $cmd = array_merge([$script], $args);

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logFile ?? ($logDir . '/onboarding.log');
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
        $message = sprintf(
            'runBackgroundProcess failed to start "%s": %s',
            $escapedCommand,
            $e->getMessage()
        );

        error_log($message);
        file_put_contents(
            $logFile,
            '[' . date('c') . '] ERROR ' . $message . PHP_EOL,
            FILE_APPEND
        );

        throw $e instanceof \RuntimeException ? $e : new \RuntimeException($message, 0, $e);
    }
}

/**
 * Run a shell script synchronously and capture its output.
 * Falls back to exec if the Symfony Process component is unavailable.
 *
 * @param string $script The script to run
 * @param array $args Arguments passed to the script
 * @param bool $throwOnError Throw an exception on failure instead of returning the error output
 * @param string|null $workingDirectory Working directory passed to the process
 *
 * @return array{success: bool, stdout: string, stderr: string}
 */
function runSyncProcess(
    string $script,
    array $args = [],
    bool $throwOnError = false,
    ?string $workingDirectory = null
): array {
    $cmd = array_merge([$script], $args);

    try {
        if (!class_exists(Process::class)) {
            throw new \RuntimeException('Symfony Process component is unavailable.');
        }

        $process = new Process($cmd, $workingDirectory);
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

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open($cmd, $descriptorSpec, $pipes, $workingDirectory, null, ['bypass_shell' => true]);

        if (!\is_resource($process)) {
            $message = 'runSyncProcess proc_open fallback failed to start process';
            error_log($message);

            if ($throwOnError) {
                throw new \RuntimeException($message, 0, $e);
            }

            return [
                'success' => false,
                'stdout' => '',
                'stderr' => $message,
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $stdout = $stdout === false ? '' : $stdout;
        $stderr = $stderr === false ? '' : $stderr;

        $exitCode = proc_close($process);
        $success = $exitCode === 0;

        if (!$success) {
            $message = $stderr !== '' ? $stderr : $stdout;
            $message = $message === ''
                ? sprintf('Process exited with code %d', $exitCode)
                : $message;

            error_log(sprintf('runSyncProcess proc_open fallback failed (exit code %d): %s', $exitCode, $message));

            if ($throwOnError) {
                throw new \RuntimeException($message, 0, $e);
            }
        }

        return [
            'success' => $success,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
