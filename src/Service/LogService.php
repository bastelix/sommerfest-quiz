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
    public static function create(string $channel = 'app'): LoggerInterface
    {
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
    public static function tail(string $channel, int $lines = 20): string
    {
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
    public static function tailDocker(string $container, int $lines = 20): string
    {
        $result = runSyncProcess('docker', ['logs', '--tail', (string) $lines, $container]);
        if ($result['stdout'] !== '') {
            return $result['stdout'];
        }
        return $result['stderr'];
    }
}
