<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use App\Infrastructure\Database;
use App\Service\TenantService;
use App\Support\EnvLoader;
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
    /** @var array<string,string> */
    private array $config;

    private ?MailerInterface $mailer = null;

    private string $fromAddress = '';

    private bool $configured = false;

    /** @var string[] */
    private array $missingConfig = [];

    public function __construct(?MailerInterface $mailer = null)
    {
        $this->config = self::loadEnvConfig();
        $this->configured = $this->determineConfiguration();
        $this->fromAddress = $this->resolveFromAddress();

        if ($mailer !== null) {
            $this->mailer = $mailer;
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

        if ($email->getFrom()->count() === 0 && $this->fromAddress !== '') {
            $email->from($this->fromAddress);
        }

        $mailer->send($email);
    }

    public function subscribe(string $email, array $data = []): void
    {
        // Brevo API integration is not implemented yet.
    }

    public function unsubscribe(string $email): void
    {
        // Brevo API integration is not implemented yet.
    }

    public function getStatus(): array
    {
        return [
            'name' => 'brevo',
            'configured' => $this->configured,
            'from_address' => $this->fromAddress,
            'missing' => $this->missingConfig,
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
        ];
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

        $result = $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;

        if ($result === '') {
            if (!in_array('SMTP_FROM', $this->missingConfig, true)) {
                $this->missingConfig[] = 'SMTP_FROM';
            }
            $this->configured = false;
        }

        return $result;
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
