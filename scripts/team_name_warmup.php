<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database;
use App\Repository\TeamNameAiCacheRepository;
use App\Service\RagChat\HttpChatResponder;
use App\Service\RagChat\OpenAiChatResponder;
use App\Service\TeamNameAiClient;
use App\Service\TeamNameService;

try {
    $argv = $argv ?? [];
    if (count($argv) < 6) {
        throw new RuntimeException('Missing arguments for team name warm-up dispatcher.');
    }

    [$script, $eventId, $domainsPayload, $tonesPayload, $localeArg, $countArg] = array_pad($argv, 6, '');

    if ($eventId === '') {
        throw new RuntimeException('Event identifier must not be empty.');
    }

    $domains = decodeList($domainsPayload);
    $tones = decodeList($tonesPayload);
    $locale = trim((string) $localeArg);
    $locale = $locale === '' ? null : $locale;

    $count = (int) $countArg;
    if ($count <= 0) {
        $count = 5;
    }

    $schemaEnv = getenv('APP_TENANT_SCHEMA');
    $schema = $schemaEnv === false || trim((string) $schemaEnv) === '' ? 'public' : (string) $schemaEnv;

    $pdo = Database::connectWithSchema($schema);

    [$teamNameAiClient, $teamNameAiEnabled] = buildAiClient();

    $repository = new TeamNameAiCacheRepository($pdo);
    $service = new TeamNameService(
        $pdo,
        __DIR__ . '/../resources/team-names/lexicon.json',
        $repository,
        600,
        $teamNameAiClient,
        $teamNameAiEnabled,
        null,
        null
    );

    $service->warmUpAiSuggestions($eventId, $domains, $tones, $locale, $count);
} catch (Throwable $exception) {
    $message = '[' . date('c') . '] Team name warm-up failed: ' . $exception->getMessage() . PHP_EOL;
    fwrite(STDERR, $message);
    exit(1);
}

exit(0);

/**
 * @return list<string>
 */
function decodeList(string $payload): array
{
    if ($payload === '') {
        return [];
    }

    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return [];
    }

    try {
        $list = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Invalid warm-up payload: ' . $exception->getMessage(), 0, $exception);
    }

    if (!is_array($list)) {
        return [];
    }

    $normalised = [];
    foreach ($list as $value) {
        if (is_string($value)) {
            $normalised[] = $value;
        }
    }

    return $normalised;
}

/**
 * @return array{0: ?TeamNameAiClient, 1: bool}
 */
function buildAiClient(): array
{
    $teamNameAiClient = null;
    $teamNameAiEnabled = true;
    $teamNameAiModelEnv = getenv('RAG_CHAT_SERVICE_MODEL');

    try {
        $endpointEnv = getenv('RAG_CHAT_SERVICE_URL');
        $endpoint = $endpointEnv !== false ? trim((string) $endpointEnv) : '';
        if ($endpoint === '') {
            throw new RuntimeException('Chat service URL is not configured.');
        }

        $tokenEnv = getenv('RAG_CHAT_SERVICE_TOKEN');
        $token = $tokenEnv !== false ? trim((string) $tokenEnv) : null;
        $token = $token === '' ? null : $token;

        $driverEnv = getenv('RAG_CHAT_SERVICE_DRIVER');
        $forceOpenAiEnv = getenv('RAG_CHAT_SERVICE_FORCE_OPENAI');
        $modelEnv = $teamNameAiModelEnv !== false ? trim((string) $teamNameAiModelEnv) : null;

        $isTruthy = static function (?string $value): bool {
            if ($value === null) {
                return false;
            }

            $normalised = strtolower(trim($value));

            return $normalised !== '' && in_array($normalised, ['1', 'true', 'yes', 'on'], true);
        };

        $shouldUseOpenAi = false;
        if ($driverEnv !== false) {
            $normalisedDriver = strtolower(trim((string) $driverEnv));
            if ($normalisedDriver === 'openai') {
                $shouldUseOpenAi = true;
            } elseif ($normalisedDriver !== '') {
                $shouldUseOpenAi = false;
            }
        }

        if (!$shouldUseOpenAi) {
            $parts = parse_url($endpoint);
            if (is_array($parts)) {
                $host = $parts['host'] ?? null;
                if (is_string($host) && $host === 'api.openai.com') {
                    $shouldUseOpenAi = true;
                }

                if (!$shouldUseOpenAi) {
                    $pathValue = $parts['path'] ?? null;
                    $path = is_string($pathValue) ? rtrim($pathValue, '/') : '';
                    if ($path === '/v1' || $path === '/v1/models' || str_ends_with($path, '/v1/chat/completions')) {
                        $shouldUseOpenAi = true;
                    }
                }
            }
        }

        if (!$shouldUseOpenAi && $isTruthy($forceOpenAiEnv !== false ? (string) $forceOpenAiEnv : null)) {
            $shouldUseOpenAi = true;
        }

        if ($shouldUseOpenAi) {
            $normalizeOpenAiEndpoint = static function (string $value): string {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    return $value;
                }

                $parts = parse_url($trimmed);
                if ($parts === false) {
                    return $value;
                }

                $scheme = $parts['scheme'] ?? null;
                $host = $parts['host'] ?? null;
                if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
                    return $value;
                }

                $pathValue = $parts['path'] ?? null;
                $path = is_string($pathValue) ? $pathValue : '';
                $normalisePath = static function (string $path): string {
                    $normalised = rtrim($path, '/');
                    if ($normalised === '' || $normalised === '/v1' || $normalised === '/v1/models') {
                        return '/v1/chat/completions';
                    }

                    if (str_ends_with($normalised, '/v1/chat/completions')) {
                        return $normalised;
                    }

                    return $path === '' ? '/v1/chat/completions' : $path;
                };

                $rebuilt = $scheme . '://';

                $userInfo = '';
                $user = $parts['user'] ?? null;
                if (is_string($user) && $user !== '') {
                    $userInfo = $user;
                    $pass = $parts['pass'] ?? null;
                    if (is_string($pass)) {
                        $userInfo .= ':' . $pass;
                    }
                    $userInfo .= '@';
                }

                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $rebuilt .= $userInfo . $host . $port . $normalisePath($path);

                $query = $parts['query'] ?? null;
                if (is_string($query) && $query !== '') {
                    $rebuilt .= '?' . $query;
                }

                $fragment = $parts['fragment'] ?? null;
                if (is_string($fragment) && $fragment !== '') {
                    $rebuilt .= '#' . $fragment;
                }

                return $rebuilt;
            };

            $options = [];
            $temperatureEnv = getenv('RAG_CHAT_SERVICE_TEMPERATURE');
            $temperature = $temperatureEnv !== false ? trim((string) $temperatureEnv) : '';
            if ($temperature !== '' && is_numeric($temperature)) {
                $options['temperature'] = (float) $temperature;
            }

            $topPEnv = getenv('RAG_CHAT_SERVICE_TOP_P');
            $topP = $topPEnv !== false ? trim((string) $topPEnv) : '';
            if ($topP !== '' && is_numeric($topP)) {
                $options['top_p'] = (float) $topP;
            }

            $presenceEnv = getenv('RAG_CHAT_SERVICE_PRESENCE_PENALTY');
            $presence = $presenceEnv !== false ? trim((string) $presenceEnv) : '';
            if ($presence !== '' && is_numeric($presence)) {
                $options['presence_penalty'] = (float) $presence;
            }

            $frequencyEnv = getenv('RAG_CHAT_SERVICE_FREQUENCY_PENALTY');
            $frequency = $frequencyEnv !== false ? trim((string) $frequencyEnv) : '';
            if ($frequency !== '' && is_numeric($frequency)) {
                $options['frequency_penalty'] = (float) $frequency;
            }

            $maxTokensEnv = getenv('RAG_CHAT_SERVICE_MAX_COMPLETION_TOKENS');
            $maxTokens = $maxTokensEnv !== false ? trim((string) $maxTokensEnv) : '';
            if ($maxTokens !== '' && is_numeric($maxTokens)) {
                $options['max_completion_tokens'] = (int) $maxTokens;
            }

            $responder = new OpenAiChatResponder(
                $normalizeOpenAiEndpoint($endpoint),
                null,
                $token,
                null,
                $modelEnv,
                $options === [] ? null : $options
            );
        } else {
            $responder = new HttpChatResponder($endpoint, null, $token);
        }

        $teamNameAiClient = new TeamNameAiClient($responder, $modelEnv);
    } catch (RuntimeException $exception) {
        $teamNameAiClient = null;
        $teamNameAiEnabled = false;
    }

    if ($teamNameAiClient === null) {
        $teamNameAiEnabled = false;
    }

    return [$teamNameAiClient, $teamNameAiEnabled];
}
