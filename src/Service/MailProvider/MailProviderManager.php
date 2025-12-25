<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use App\Infrastructure\Database;
use App\Infrastructure\MailProviderRepository;
use App\Service\PageService;
use App\Service\SettingsService;
use RuntimeException;
use Symfony\Component\Mime\Email;

class MailProviderManager
{
    /**
     * @var array<string,callable(array<string,mixed>):MailProviderInterface>
     */
    private array $factories;

    private ?MailProviderInterface $activeProvider = null;

    private ?MailProviderRepository $repository;

    /** @var array<string,mixed>|null */
    private ?array $activeConfig = null;

    private string $namespace;

    public function __construct(
        SettingsService $settings,
        array $factories = [],
        ?MailProviderRepository $repository = null,
        ?string $namespace = null
    ) {
        $this->namespace = $this->normalizeNamespace($namespace);
        if ($repository instanceof MailProviderRepository) {
            $this->repository = $repository;
        } else {
            try {
                $this->repository = new MailProviderRepository($settings->getConnection());
            } catch (RuntimeException $exception) {
                $this->repository = null;
            }
        }
        $this->factories = $factories + [
            'brevo' => static fn (array $config = []): MailProviderInterface => new BrevoProvider(null, $config),
            'mailchimp' => static fn (array $config = []): MailProviderInterface => new MailchimpProvider(),
            'sendgrid' => static fn (array $config = []): MailProviderInterface => new SendgridProvider(),
        ];
    }

    public function sendMail(Email $email, array $options = []): void
    {
        $this->getProvider()->sendMail($email, $options);
    }

    public function subscribe(string $email, array $data = []): void
    {
        $this->getProvider()->subscribe($email, $data);
    }

    public function unsubscribe(string $email): void
    {
        $this->getProvider()->unsubscribe($email);
    }

    public function getStatus(): array
    {
        return $this->getProvider()->getStatus();
    }

    public function isConfigured(): bool
    {
        try {
            $status = $this->getStatus();
        } catch (RuntimeException $exception) {
            return false;
        }

        return (bool) ($status['configured'] ?? false);
    }

    public function refresh(): void
    {
        $this->activeProvider = null;
        $this->activeConfig = null;
    }

    public static function isConfiguredStatic(): bool
    {
        $pdo = Database::connectFromEnv();
        $settings = new SettingsService($pdo);
        $manager = new self($settings);

        return $manager->isConfigured();
    }

    /**
     * @param array<string,mixed> $config
     */
    public function createProvider(string $name, array $config = []): MailProviderInterface
    {
        $name = strtolower($name);
        if (!isset($this->factories[$name])) {
            throw new RuntimeException('Invalid mail provider configuration.');
        }

        $factory = $this->factories[$name];
        /** @var MailProviderInterface $provider */
        $provider = $factory($this->normalizeConfig($config));

        return $provider;
    }

    private function getProvider(): MailProviderInterface
    {
        if ($this->activeProvider instanceof MailProviderInterface) {
            return $this->activeProvider;
        }

        $config = $this->activeConfig;
        if ($config === null) {
            if ($this->repository instanceof MailProviderRepository) {
                $config = $this->repository->findActive($this->namespace)
                    ?? $this->repository->find('brevo', $this->namespace);
            }
            if ($config === null) {
                throw new RuntimeException(
                    sprintf('No mail provider configured for namespace "%s".', $this->namespace)
                );
            }
            $this->activeConfig = $config;
        }

        $name = strtolower((string) ($config['provider_name'] ?? 'brevo'));
        $provider = $this->createProvider($name, $config);
        $this->activeProvider = $provider;

        return $provider;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $settings = $config['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'mailer_dsn' => (string) ($settings['mailer_dsn'] ?? ''),
            'host' => (string) ($config['smtp_host'] ?? ''),
            'user' => (string) ($config['smtp_user'] ?? ''),
            'pass' => (string) ($config['smtp_pass'] ?? ''),
            'port' => (string) ($config['smtp_port'] ?? ''),
            'encryption' => (string) ($config['smtp_encryption'] ?? ''),
            'from' => (string) ($settings['from_email'] ?? ''),
            'from_name' => (string) ($settings['from_name'] ?? ''),
            'api_key' => (string) ($config['api_key'] ?? ''),
            'list_id' => (string) ($config['list_id'] ?? ''),
        ];
    }

    private function normalizeNamespace(?string $namespace): string
    {
        $normalized = is_string($namespace) ? strtolower(trim($namespace)) : '';

        return $normalized !== '' ? $normalized : PageService::DEFAULT_NAMESPACE;
    }
}
