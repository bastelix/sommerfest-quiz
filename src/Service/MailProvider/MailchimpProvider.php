<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use RuntimeException;
use Symfony\Component\Mime\Email;

/**
 * Placeholder for future Mailchimp integration.
 */
class MailchimpProvider implements MailProviderInterface
{
    public function sendMail(Email $email, array $options = []): void
    {
        throw new RuntimeException('Mailchimp provider is not implemented yet.');
    }

    public function subscribe(string $email, array $data = []): void
    {
        throw new RuntimeException('Mailchimp provider is not implemented yet.');
    }

    public function unsubscribe(string $email): void
    {
        throw new RuntimeException('Mailchimp provider is not implemented yet.');
    }

    public function getStatus(): array
    {
        return [
            'name' => 'mailchimp',
            'configured' => false,
        ];
    }
}
