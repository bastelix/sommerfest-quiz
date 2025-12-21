<?php

declare(strict_types=1);

namespace App\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

use function App\runSyncProcess;

/**
 * Factory for Monolog loggers that write to the application's log directory.
 */
class LogService
{
    /**
     * Create a logger instance for the given channel.
     */
    public static function create(string $channel = 'app'): LoggerInterface {
        $root = dirname(__DIR__, 2);
        $logDir = $root . '/logs';
        if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            throw new \RuntimeException('Unable to create log directory');
        }
        if (!is_writable($logDir)) {
            throw new \RuntimeException('Log directory not writable');
        }
        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler($logDir . '/' . $channel . '.log', Level::Debug));
        return $logger;
    }

    /**
     * Fetch the most recent log lines for the given channel.
     */
    public static function tail(string $channel, int $lines = 20): string {
        $root = dirname(__DIR__, 2);
        $file = $root . '/logs/' . $channel . '.log';
        if (!is_file($file)) {
            return '';
        }
        $content = file($file);
        if ($content === false) {
            return '';
        }
        return implode('', array_slice($content, -$lines));
    }

    /**
     * Fetch the most recent Docker log lines for the given container.
     */
    public static function tailDocker(string $container, int $lines = 20): string {
        if (self::hasDockerBinary()) {
            $result = runSyncProcess('docker', ['logs', '--tail', (string) $lines, $container]);
            if ($result['stdout'] !== '') {
                return $result['stdout'];
            }
            if ($result['stderr'] === '' || self::isDockerUnavailableError($result['stderr'])) {
                return self::tailDockerViaSocket($container, $lines);
            }
            return $result['stderr'];
        }

        return self::tailDockerViaSocket($container, $lines);
    }

    private static function hasDockerBinary(): bool
    {
        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        foreach ($paths as $path) {
            $candidate = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docker';
            if (is_file($candidate) && is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function isDockerUnavailableError(string $stderr): bool
    {
        $needle = strtolower($stderr);
        return str_contains($needle, 'not found')
            || str_contains($needle, 'cannot execute')
            || str_contains($needle, 'permission denied');
    }

    private static function tailDockerViaSocket(string $container, int $lines): string
    {
        $socket = '/var/run/docker.sock';
        if (!file_exists($socket) || filetype($socket) !== 'socket') {
            return 'Docker socket not available.';
        }

        $url = sprintf(
            'http://localhost/containers/%s/logs?stdout=1&stderr=1&tail=%d',
            rawurlencode($container),
            $lines
        );

        $ch = curl_init();
        if ($ch === false) {
            return 'Failed to initialize Docker log request.';
        }

        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return $error !== '' ? sprintf('Docker log request failed: %s', $error) : 'Docker log request failed.';
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status >= 400) {
            return sprintf('Docker log request failed with status %d.', $status);
        }

        return $response;
    }
}
