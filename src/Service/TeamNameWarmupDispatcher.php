<?php

declare(strict_types=1);

namespace App\Service;

use JsonException;
use Throwable;

use function App\runBackgroundProcess;
use function array_values;
use function base64_encode;
use function getenv;
use function json_encode;
use function putenv;

class TeamNameWarmupDispatcher
{
    private string $scriptPath;

    private string $phpBinary;

    public function __construct(
        private ?string $tenantSchema = null,
        ?string $phpBinary = null,
        ?string $scriptPath = null
    ) {
        $this->phpBinary = $phpBinary ?? PHP_BINARY;
        $this->scriptPath = $scriptPath ?? dirname(__DIR__, 2) . '/scripts/team_name_warmup.php';
    }

    /**
     * @param array<int, string> $domains
     * @param array<int, string> $tones
     */
    public function dispatchWarmup(
        string $eventId,
        array $domains,
        array $tones,
        ?string $locale,
        int $count
    ): void {
        if ($eventId === '') {
            return;
        }

        try {
            $domainsPayload = base64_encode(json_encode(array_values($domains), JSON_THROW_ON_ERROR));
            $tonesPayload = base64_encode(json_encode(array_values($tones), JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            error_log('Unable to encode team name warm-up payload: ' . $exception->getMessage());
            return;
        }

        $arguments = [
            $this->scriptPath,
            $eventId,
            $domainsPayload,
            $tonesPayload,
            $locale ?? '',
            (string) $count,
        ];

        $previousSchema = getenv('APP_TENANT_SCHEMA');
        $shouldRestoreSchema = $this->tenantSchema !== null;
        if ($this->tenantSchema !== null && $this->tenantSchema !== '') {
            putenv('APP_TENANT_SCHEMA=' . $this->tenantSchema);
        }

        try {
            runBackgroundProcess($this->phpBinary, $arguments);
        } catch (Throwable $exception) {
            error_log('Failed to dispatch team name warm-up: ' . $exception->getMessage());
        } finally {
            if ($shouldRestoreSchema) {
                if ($previousSchema === false) {
                    putenv('APP_TENANT_SCHEMA');
                } else {
                    putenv('APP_TENANT_SCHEMA=' . $previousSchema);
                }
            }
        }
    }
}
