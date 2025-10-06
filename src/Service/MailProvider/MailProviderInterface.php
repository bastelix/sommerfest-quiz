<?php

declare(strict_types=1);

namespace App\Service\MailProvider;

use Symfony\Component\Mime\Email;

interface MailProviderInterface
{
    /**
     * @param array<string,mixed> $options
     */
    public function sendMail(Email $email, array $options = []): void;

    /**
     * @param array<string,mixed> $data
     */
    public function subscribe(string $email, array $data = []): void;

    public function unsubscribe(string $email): void;

    /**
     * @return array<string,mixed>
     */
    public function getStatus(): array;
}
