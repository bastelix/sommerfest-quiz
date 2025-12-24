<?php

declare(strict_types=1);

namespace App\Service;

use Throwable;

use function chmod;
use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function curl_version;
use function defined;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_array;
use function is_dir;
use function is_writable;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function dirname;
use function getenv;

use const CURL_IPRESOLVE_V4;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_IPRESOLVE;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_USERPWD;

/**
 * @phpstan-type ProvenExpertCache array<string,mixed>
 */
class ProvenExpertRatingService
{
    private const API_URL = 'https://www.provenexpert.com/api_rating_v2.json';
    private const SCRIPT_VERSION = '1.8';
    private const CACHE_FILENAME = 'provenexpert_92e095e9afee5f761169703fff4730ee.json';
    private const ERROR_FILENAME = 'provenexpert_error.txt';
    private const CACHE_LIFETIME = 3600;

    private string $apiId;
    private string $apiKey;
    private string $cacheFile;
    private string $errorFile;
    private ?string $caInfo;
    private ?string $caPath;

    public function __construct(?string $apiId = null, ?string $apiKey = null, ?string $cacheDirectory = null) {
        $this->apiId = $apiId ?? getenv('PROVENEXPERT_API_ID') ?: '1tmo5LmpmpQpmqGB1xGAiAmZ38zZmV3o';
        $this->apiKey = $apiKey ?? getenv('PROVENEXPERT_API_KEY') ?: 'AGyuLJEzAmx4BJV5LJDjLwEvMQMyLmxjBGuwAmRjBGp';

        $caInfo = getenv('PROVENEXPERT_CAINFO');
        $caPath = getenv('PROVENEXPERT_CAPATH');
        $this->caInfo = $caInfo !== false && $caInfo !== '' ? $caInfo : null;
        $this->caPath = $caPath !== false && $caPath !== '' ? $caPath : null;

        $directory = $cacheDirectory ?? sys_get_temp_dir();
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $this->cacheFile = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CACHE_FILENAME;
        $this->errorFile = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::ERROR_FILENAME;

        if (!file_exists($this->cacheFile)) {
            @touch($this->cacheFile);
            @chmod($this->cacheFile, 0666);
        }
    }

    public function getAggregateRatingMarkup(): string {
        $data = $this->loadData();
        if (($data['status'] ?? null) === 'success' && isset($data['aggregateRating'])) {
            $markup = (string) $data['aggregateRating'];
            if ($markup !== '') {
                return $markup;
            }
        }

        return '<!-- provenexpert response error [v' . self::SCRIPT_VERSION . '] -->';
    }

    /**
     * @return array<string,mixed>
     */
    private function loadData(): array {
        $cached = $this->readCache();
        $cacheFresh = $this->isCacheFresh();

        if ($cacheFresh && $cached !== null) {
            return $cached;
        }

        $fetched = $this->fetch();
        if ($fetched !== null) {
            if (($fetched['status'] ?? null) === 'success') {
                $this->writeCache($fetched);
                return $fetched;
            }

            if ($cached !== null) {
                $this->touchCache();
                return $cached;
            }

            return $fetched;
        }

        if ($cached !== null) {
            return $cached;
        }

        $fallback = ['status' => 'error'];
        $this->writeCache($fallback);

        return $fallback;
    }

    private function fetch(): ?array {
        if (!function_exists('curl_init')) {
            $this->logError('no curl package installed');
            return null;
        }

        $handler = curl_init();
        if ($handler === false) {
            $this->logError('curl init failed');
            return null;
        }

        $query = sprintf('?v=%s', self::SCRIPT_VERSION);
        curl_setopt($handler, CURLOPT_TIMEOUT, 2);
        curl_setopt($handler, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, true);
        if ($this->caInfo !== null) {
            curl_setopt($handler, CURLOPT_CAINFO, $this->caInfo);
        }
        if ($this->caPath !== null) {
            curl_setopt($handler, CURLOPT_CAPATH, $this->caPath);
        }
        curl_setopt($handler, CURLOPT_URL, self::API_URL . $query);
        curl_setopt($handler, CURLOPT_USERPWD, $this->apiId . ':' . $this->apiKey);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($handler, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        try {
            $json = curl_exec($handler);
        } catch (Throwable $exception) {
            $this->logError('curl exec exception' . PHP_EOL . PHP_EOL . $exception->getMessage());
            curl_close($handler);
            return null;
        }
        if ($json === false) {
            $this->logCurlError($handler);
            curl_close($handler);
            return null;
        }
        curl_close($handler);

        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            $this->logError('json error' . PHP_EOL . PHP_EOL . (string) $json);
            return null;
        }

        return $data;
    }

    /**
     * @return ProvenExpertCache|null
     */
    private function readCache(): ?array {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $contents = @file_get_contents($this->cacheFile);
        if ($contents === false || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var ProvenExpertCache $decoded */
        return $decoded;
    }

    /**
     * @param ProvenExpertCache $data
     */
    private function writeCache(array $data): void {
        if (!is_writable($this->cacheFile) && !is_writable(dirname($this->cacheFile))) {
            return;
        }

        $encoded = json_encode($data);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($this->cacheFile, $encoded);
    }

    private function touchCache(): void {
        @touch($this->cacheFile, time() - (int) (self::CACHE_LIFETIME / 10));
    }

    private function isCacheFresh(): bool {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $mtime = filemtime($this->cacheFile);
        if ($mtime === false) {
            return false;
        }

        return (time() - $mtime) <= self::CACHE_LIFETIME;
    }

    private function logError(string $message): void {
        $log = sprintf('%s [v%s]', $message, self::SCRIPT_VERSION);
        @file_put_contents($this->errorFile, $log);
    }

    /**
     * @param resource $handler
     */
    private function logCurlError($handler): void {
        $errorMessage = 'curl error';
        $error = curl_error($handler);
        if ($error !== '') {
            $errorMessage .= PHP_EOL . PHP_EOL . $error;
        }

        $version = curl_version();
        if (is_array($version)) {
            $errorMessage .= PHP_EOL . PHP_EOL . json_encode($version);
        }

        $this->logError($errorMessage);
    }
}
