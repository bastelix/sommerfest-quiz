<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use App\Infrastructure\Database;
use App\Service\SettingsService;
use RuntimeException;
use Symfony\Component\Mime\Email;

class MailProviderManager
{
    private SettingsService $settings;

    /** @var array<string,callable():MailProviderInterface> */
    private array $factories;

    private ?MailProviderInterface $activeProvider = null;

    public function __construct(SettingsService $settings, array $factories = [])
    {
        $this->settings = $settings;
        $this->factories = $factories + [
            'brevo' => static fn (): MailProviderInterface => new BrevoProvider(),
            'mailchimp' => static fn (): MailProviderInterface => new MailchimpProvider(),
            'sendgrid' => static fn (): MailProviderInterface => new SendgridProvider(),
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
        $status = $this->getStatus();

        return (bool) ($status['configured'] ?? false);
    }

    public function refresh(): void
    {
        $this->activeProvider = null;
    }

    public static function isConfiguredStatic(): bool
    {
        $pdo = Database::connectFromEnv();
        $settings = new SettingsService($pdo);
        $manager = new self($settings);

        return $manager->isConfigured();
    }

    private function getProvider(): MailProviderInterface
    {
        if ($this->activeProvider instanceof MailProviderInterface) {
            return $this->activeProvider;
        }

        $name = strtolower((string) $this->settings->get('mail_provider', 'brevo'));
        if (!isset($this->factories[$name])) {
            $name = 'brevo';
        }

        $factory = $this->factories[$name];
        $provider = $factory();

        $this->activeProvider = $provider;

        return $provider;
    }
}
