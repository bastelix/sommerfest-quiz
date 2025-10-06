<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use RuntimeException;
use Symfony\Component\Mime\Email;

/**
 * Placeholder for future Sendgrid integration.
 */
class SendgridProvider implements MailProviderInterface
{
    public function sendMail(Email $email, array $options = []): void
    {
        throw new RuntimeException('Sendgrid provider is not implemented yet.');
    }

    public function subscribe(string $email, array $data = []): void
    {
        throw new RuntimeException('Sendgrid provider is not implemented yet.');
    }

    public function unsubscribe(string $email): void
    {
        throw new RuntimeException('Sendgrid provider is not implemented yet.');
    }

    public function getStatus(): array
    {
        return [
            'name' => 'sendgrid',
            'configured' => false,
        ];
    }
}
