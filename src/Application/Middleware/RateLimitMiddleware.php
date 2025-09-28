<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private const APCU_PREFIX = 'rlm:';
    private const FILE_PREFIX = 'rlm_';

    private int $maxRequests;
    private int $windowSeconds;
    private static ?string $storageDir = null;
    /** @var array<string, true> */
    private static array $apcuKeys = [];

    public function __construct(int $maxRequests = 5, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $key = 'rate:' . $request->getUri()->getPath();
        $entry = $_SESSION[$key] ?? ['count' => 0, 'start' => time()];

        if (time() - $entry['start'] > $this->windowSeconds) {
            $entry = ['count' => 0, 'start' => time()];
        }

        $entry['count']++;
        $_SESSION[$key] = $entry;

        if ($entry['count'] > $this->maxRequests) {
            return $this->tooManyRequestsResponse();
        }

        $persistentCount = $this->incrementPersistentCounter($request);
        if ($persistentCount > $this->maxRequests) {
            return $this->tooManyRequestsResponse();
        }

        return $handler->handle($request);
    }

    /**
     * Clear all persistent counters. Intended for use in tests.
     */
    public static function resetPersistentStorage(): void
    {
        if (self::apcuAvailable()) {
            if (class_exists('\\APCUIterator')) {
                /** @var iterable<array{key:string}> $iterator */
                $iterator = new \APCUIterator('/^' . preg_quote(self::APCU_PREFIX, '/') . '/');
                foreach ($iterator as $item) {
                    apcu_delete($item['key']);
                }
            } else {
                foreach (array_keys(self::$apcuKeys) as $key) {
                    apcu_delete($key);
                }
            }
            self::$apcuKeys = [];
        }

        $dir = self::$storageDir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit');
        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . self::FILE_PREFIX . '*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    private function tooManyRequestsResponse(): Response
    {
        $response = new SlimResponse(429);

        return $response->withHeader('Retry-After', (string) $this->windowSeconds);
    }

    private function incrementPersistentCounter(Request $request): int
    {
        $now = time();
        $hash = $this->fingerprintRequest($request);

        if (self::apcuAvailable()) {
            $key = self::APCU_PREFIX . $hash;
            $entry = apcu_fetch($key, $success);
            if (!$success || !is_array($entry) || $this->isExpiredEntry($entry, $now)) {
                $entry = ['count' => 0, 'start' => $now];
            }

            $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
            $entry['start'] = (int) ($entry['start'] ?? $now);
            apcu_store($key, $entry, $this->windowSeconds);
            self::$apcuKeys[$key] = true;

            return (int) $entry['count'];
        }

        $path = $this->getFilePath($hash);
        $entry = $this->readFileEntry($path, $now);
        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        $entry['start'] = (int) ($entry['start'] ?? $now);
        $this->writeFileEntry($path, $entry);

        return (int) $entry['count'];
    }

    /**
     * @param array<string, int> $entry
     */
    private function isExpiredEntry(array $entry, int $now): bool
    {
        if (!isset($entry['start'])) {
            return true;
        }

        return ($now - (int) $entry['start']) > $this->windowSeconds;
    }

    private function fingerprintRequest(Request $request): string
    {
        $serverParams = $request->getServerParams();
        $ip = (string) ($serverParams['REMOTE_ADDR'] ?? 'unknown');
        $ua = $request->getHeaderLine('User-Agent');
        if ($ua === '') {
            $ua = (string) ($serverParams['HTTP_USER_AGENT'] ?? '');
        }
        $ua = trim($ua);
        if ($ua === '') {
            $ua = 'no-ua';
        }

        $path = strtolower($request->getUri()->getPath());

        return hash('sha256', $path . '|' . $ip . '|' . $ua);
    }

    /**
     * @return array<string, int>
     */
    private function readFileEntry(string $path, int $now): array
    {
        if (is_file($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                $data = json_decode($contents, true);
                if (is_array($data) && !$this->isExpiredEntry($data, $now)) {
                    return [
                        'count' => (int) ($data['count'] ?? 0),
                        'start' => (int) ($data['start'] ?? $now),
                    ];
                }
            }
        }

        return ['count' => 0, 'start' => $now];
    }

    /**
     * @param array<string, int> $entry
     */
    private function writeFileEntry(string $path, array $entry): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($entry), LOCK_EX);
    }

    private function getFilePath(string $hash): string
    {
        $dir = self::$storageDir;
        if ($dir === null) {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limit';
            self::$storageDir = $dir;
        }

        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::FILE_PREFIX . $hash . '.json';
    }

    private static function apcuAvailable(): bool
    {
        return function_exists('apcu_fetch')
            && function_exists('apcu_store')
            && function_exists('apcu_delete')
            && (!function_exists('apcu_enabled') || apcu_enabled());
    }
}
