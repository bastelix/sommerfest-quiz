<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Simple wrapper around Symfony Mailer.
 */
class MailService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private string $from;

    public function __construct(Environment $twig)
    {
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';
        $env = [];

        if (is_readable($envFile)) {
            $env = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
        }

        $host = (string) ($env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '');
        $user = (string) ($env['SMTP_USER'] ?? getenv('SMTP_USER') ?: '');
        $pass = (string) ($env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
        $port = (string) ($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '587');

        $dsn = sprintf('smtp://%s:%s@%s:%s', rawurlencode($user), rawurlencode($pass), $host, $port);

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
        $this->twig   = $twig;
        $this->from   = $user;
    }

    /**
     * Send password reset mail with link.
     */
    public function sendPasswordReset(string $to, string $link): void
    {
        $html = $this->twig->render('emails/password_reset.twig', ['link' => $link]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('Passwort zurücksetzen')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Send double opt-in email with confirmation link.
     */
    public function sendDoubleOptIn(string $to, string $link): void
    {
        $html = $this->twig->render('emails/double_optin.twig', ['link' => $link]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('E-Mail bestätigen')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Send initial welcome mail with admin credentials.
     *
     * Returns the rendered HTML (useful for logging/preview).
     *
     * @return string Rendered HTML content of the email
     */
    public function sendWelcome(string $to, string $domain, string $password): string
    {
        $html = $this->twig->render('emails/welcome.twig', [
            'domain'   => $domain,
            'password' => $password,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('Willkommen bei QuizRace')
            ->html($html);

        $this->mailer->send($email);

        return $html;
    }
}
