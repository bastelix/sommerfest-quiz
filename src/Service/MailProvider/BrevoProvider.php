<?php
declare(strict_types=1);

namespace App\Service\MailProvider;

use App\Infrastructure\Database;
use App\Service\TenantService;
use App\Support\EnvLoader;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Brevo-based mail provider with optional SMTP fallback.
 */
class BrevoProvider implements MailProviderInterface
{
    /**
     * @var array<string,string>
     */
    private array $config;

    private ?MailerInterface $mailer = null;

    private string $fromAddress = '';

    private bool $configured = false;

    /** @var string[] */
    private array $missingConfig = [];

    private ?ClientInterface $httpClient = null;

    /**
     * @param array<string,mixed> $configOverride
     */
    public function __construct(
        ?MailerInterface $mailer = null,
        array $configOverride = [],
        ?ClientInterface $httpClient = null
    ) {
        $this->config = self::loadEnvConfig();
        $this->applyOverrides($configOverride);
        $this->configured = $this->determineConfiguration();
        $this->fromAddress = $this->resolveFromAddress();

        if ($mailer !== null) {
            $this->mailer = $mailer;
        }
        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        }
    }

    public function sendMail(Email $email, array $options = []): void
    {
        if (!$this->configured) {
            throw new RuntimeException('Mail provider is not configured.');
        }

        $mailer = $this->mailer ?? $this->createDefaultMailer();

        if (isset($options['smtp_override']) && is_array($options['smtp_override'])) {
            $override = $this->buildOverrideMailer($options['smtp_override']);
            if ($override instanceof MailerInterface) {
                $mailer = $override;
            }
        }

        $fromAddresses = $email->getFrom();
        if ($fromAddresses === [] && $this->fromAddress !== '') {
            $email->from($this->fromAddress);
        }

        $mailer->send($email);
    }

    public function subscribe(string $email, array $data = []): void
    {
        $client = $this->getHttpClient();
        $headers = $this->buildApiHeaders();

        $payload = [
            'email' => $email,
            'updateEnabled' => true,
            'listIds' => $this->resolveListIds(),
        ];

        $attributes = $this->filterAttributes($data);
        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
        }

        try {
            $client->request('POST', 'contacts', [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => 5.0,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                'Failed to subscribe contact via Brevo: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    public function unsubscribe(string $email): void
    {
        $client = $this->getHttpClient();
        $headers = $this->buildApiHeaders();
        $payload = [
            'unlinkListIds' => $this->resolveListIds(),
        ];

        try {
            $client->request('PUT', 'contacts/' . rawurlencode($email), [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => 5.0,
            ]);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            if ($response->getStatusCode() !== 404) {
                throw new RuntimeException(
                    'Failed to unsubscribe contact via Brevo: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                'Failed to unsubscribe contact via Brevo: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    public function getStatus(): array
    {
        return [
            'name' => 'brevo',
            'configured' => $this->configured,
            'from_address' => $this->fromAddress,
            'missing' => $this->missingConfig,
            'newsletter_configured' => $this->hasApiConfiguration(),
        ];
    }

    private static function loadEnvConfig(): array
    {
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';
        $env = EnvLoader::load($envFile);

        return [
            'mailer_dsn' => (string) ($env['MAILER_DSN'] ?? getenv('MAILER_DSN') ?: ''),
            'host'       => (string) ($env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: ''),
            'user'       => (string) ($env['SMTP_USER'] ?? getenv('SMTP_USER') ?: ''),
            'pass'       => (string) ($env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: ''),
            'port'       => (string) ($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '587'),
            'encryption' => (string) ($env['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'none'),
            'from'       => (string) ($env['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: ''),
            'from_name'  => (string) ($env['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: ''),
            'api_key'    => (string) ($env['MAILER_API_KEY'] ?? getenv('MAILER_API_KEY') ?: ''),
            'list_id'    => (string) ($env['MAILER_LIST_ID'] ?? getenv('MAILER_LIST_ID') ?: ''),
        ];
    }

    /**
     * @param array<string,mixed> $configOverride
     */
    private function applyOverrides(array $configOverride): void
    {
        $map = [
            'mailer_dsn' => 'mailer_dsn',
            'host' => 'host',
            'user' => 'user',
            'pass' => 'pass',
            'port' => 'port',
            'encryption' => 'encryption',
            'from' => 'from',
            'from_name' => 'from_name',
            'api_key' => 'api_key',
            'list_id' => 'list_id',
        ];

        foreach ($map as $source => $target) {
            if (!array_key_exists($source, $configOverride)) {
                continue;
            }

            $value = $configOverride[$source];
            if ($value === null) {
                continue;
            }

            $stringValue = (string) $value;
            if ($stringValue === '') {
                continue;
            }

            $this->config[$target] = $stringValue;
        }

        $settings = $configOverride['settings'] ?? [];
        if (is_array($settings)) {
            foreach (['mailer_dsn', 'from', 'from_name'] as $key) {
                if (!array_key_exists($key, $settings)) {
                    continue;
                }

                $value = (string) $settings[$key];
                if ($value !== '') {
                    $this->config[$key] = $value;
                }
            }
        }
    }

    private function determineConfiguration(): bool
    {
        $this->missingConfig = [];

        if ($this->config['mailer_dsn'] !== '') {
            return true;
        }

        $missing = [];
        if ($this->config['host'] === '') {
            $missing[] = 'SMTP_HOST';
        }
        if ($this->config['user'] === '') {
            $missing[] = 'SMTP_USER';
        }
        if ($this->config['pass'] === '') {
            $missing[] = 'SMTP_PASS';
        }

        if ($missing !== []) {
            $this->missingConfig = $missing;

            return false;
        }

        return true;
    }

    private function resolveFromAddress(): string
    {
        $fromEmail = $this->config['from'] !== '' ? $this->config['from'] : $this->config['user'];
        if ($fromEmail === '') {
            if (!in_array('SMTP_FROM', $this->missingConfig, true)) {
                $this->missingConfig[] = 'SMTP_FROM';
            }
            $this->configured = false;
            return '';
        }

        $fromName = $this->config['from_name'];
        if ($fromName === '') {
            $pdo = Database::connectFromEnv();
            $profile = (new TenantService($pdo))->getMainTenant();
            $fromName = (string) ($profile['imprint_name'] ?? '');
        }

        return $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;
    }

    private function createDefaultMailer(): MailerInterface
    {
        if ($this->mailer instanceof MailerInterface) {
            return $this->mailer;
        }

        $dsn = $this->resolveDsn();
        $this->mailer = $this->createTransport($dsn);

        return $this->mailer;
    }

    private function resolveDsn(): string
    {
        if ($this->config['mailer_dsn'] !== '') {
            return $this->config['mailer_dsn'];
        }

        $dsn = sprintf(
            'smtp://%s:%s@%s:%s',
            rawurlencode($this->config['user']),
            rawurlencode($this->config['pass']),
            $this->config['host'],
            $this->config['port']
        );

        $encryption = strtolower($this->config['encryption']);
        if ($encryption !== '' && $encryption !== 'none') {
            $dsn .= '?encryption=' . rawurlencode($encryption);
        }

        return $dsn;
    }

    private function createTransport(string $dsn): MailerInterface
    {
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    private function getHttpClient(): ClientInterface
    {
        if ($this->httpClient instanceof ClientInterface) {
            return $this->httpClient;
        }

        $this->httpClient = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
        ]);

        return $this->httpClient;
    }

    /**
     * @return array<string,string>
     */
    private function buildApiHeaders(): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        if ($apiKey === '') {
            throw new RuntimeException('Brevo API key is not configured.');
        }

        return [
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ];
    }

    /**
     * @return int[]
     */
    private function resolveListIds(): array
    {
        $raw = $this->config['list_id'] ?? '';
        if ($raw === '') {
            throw new RuntimeException('Brevo list ID is not configured.');
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, static fn (string $part): bool => $part !== '');
        $ids = [];
        foreach ($parts as $part) {
            $ids[] = (int) $part;
        }

        if ($ids === []) {
            throw new RuntimeException('Brevo list ID is not configured.');
        }

        return $ids;
    }

    /**
     * @param array<array-key,mixed> $data
     * @return array<string,scalar>
     */
    private function filterAttributes(array $data): array
    {
        $attributes = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (is_scalar($value)) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    private function hasApiConfiguration(): bool
    {
        return ($this->config['api_key'] ?? '') !== '' && ($this->config['list_id'] ?? '') !== '';
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildOverrideMailer(array $config): ?MailerInterface
    {
        $dsnValue = isset($config['smtp_dsn']) ? trim((string) $config['smtp_dsn']) : '';
        if ($dsnValue !== '') {
            try {
                return $this->createTransport($dsnValue);
            } catch (Throwable $e) {
                error_log('Failed to create domain SMTP transport: ' . $e->getMessage());

                return null;
            }
        }

        $host = isset($config['smtp_host']) ? trim((string) $config['smtp_host']) : '';
        $user = isset($config['smtp_user']) ? trim((string) $config['smtp_user']) : '';
        $pass = isset($config['smtp_pass']) ? (string) $config['smtp_pass'] : '';
        if ($host === '' || $user === '' || $pass === '') {
            return null;
        }

        $port = $config['smtp_port'] ?? null;
        if (is_string($port)) {
            $port = trim($port);
            if ($port === '') {
                $port = null;
            }
        }
        $portValue = null;
        if ($port !== null) {
            $portValue = (int) $port;
            if ($portValue <= 0) {
                $portValue = null;
            }
        }

        $dsn = sprintf(
            'smtp://%s:%s@%s',
            rawurlencode($user),
            rawurlencode($pass),
            $host
        );

        if ($portValue !== null) {
            $dsn .= ':' . $portValue;
        }

        $encryption = isset($config['smtp_encryption']) ? strtolower(trim((string) $config['smtp_encryption'])) : '';
        if ($encryption !== '' && $encryption !== 'none') {
            $dsn .= '?encryption=' . rawurlencode($encryption);
        }

        try {
            return $this->createTransport($dsn);
        } catch (Throwable $e) {
            error_log('Failed to create domain SMTP transport: ' . $e->getMessage());
        }

        return null;
    }
}
