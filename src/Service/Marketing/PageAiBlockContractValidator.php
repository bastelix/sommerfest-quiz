<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use RuntimeException;

use function is_array;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class PageAiBlockContractValidator
{
    public const ERROR_INVALID_JSON = 'invalid-json';
    public const ERROR_MISSING_META = 'missing-meta';
    public const ERROR_MISSING_BLOCKS = 'missing-blocks';
    public const ERROR_INVALID_SCHEMA = 'invalid-schema-version';

    public function validate(string $json): string
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            throw new RuntimeException(self::ERROR_INVALID_JSON);
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(self::ERROR_INVALID_JSON);
        }

        if (!isset($decoded['meta']) || !is_array($decoded['meta'])) {
            throw new RuntimeException(self::ERROR_MISSING_META);
        }

        $schemaVersion = $decoded['meta']['schemaVersion'] ?? '';
        if ($schemaVersion !== 'block-contract-v1') {
            throw new RuntimeException(self::ERROR_INVALID_SCHEMA);
        }

        if (!isset($decoded['blocks']) || !is_array($decoded['blocks']) || $decoded['blocks'] === []) {
            throw new RuntimeException(self::ERROR_MISSING_BLOCKS);
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException(self::ERROR_INVALID_JSON);
        }

        return $encoded;
    }
}
