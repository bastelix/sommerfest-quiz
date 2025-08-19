<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use App\Infrastructure\Database;
use App\Service\TenantService;
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
    private ?AuditLogger $audit;
    private string $baseUrl;

    private static function loadEnvConfig(): array
    {
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';
        $env = [];

        if (is_readable($envFile)) {
            $env = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
        }

        return [
            'host'       => (string) ($env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: ''),
            'user'       => (string) ($env['SMTP_USER'] ?? getenv('SMTP_USER') ?: ''),
            'pass'       => (string) ($env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: ''),
            'port'       => (string) ($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '587'),
            'encryption' => (string) ($env['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'none'),
            'from'       => (string) ($env['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: ''),
            'from_name'  => (string) ($env['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: ''),
        ];
    }

    public static function isConfigured(): bool
    {
        $config = self::loadEnvConfig();

        return $config['host'] !== '' && $config['user'] !== '' && $config['pass'] !== '';
    }

    public function __construct(Environment $twig, ?AuditLogger $audit = null)
    {
        $config = self::loadEnvConfig();

        $host       = $config['host'];
        $user       = $config['user'];
        $pass       = $config['pass'];
        $port       = $config['port'];
        $encryption = $config['encryption'];

        if ($host === '' || $user === '' || $pass === '') {
            $missing = [];
            if ($host === '') {
                $missing[] = 'SMTP_HOST';
            }
            if ($user === '') {
                $missing[] = 'SMTP_USER';
            }
            if ($pass === '') {
                $missing[] = 'SMTP_PASS';
            }

            throw new RuntimeException('Missing SMTP configuration: ' . implode(', ', $missing));
        }

        $pdo = Database::connectFromEnv();
        $profile = (new TenantService($pdo))->getMainTenant();

        $fromEmail = $config['from'] !== '' ? $config['from'] : $user;
        $fromName  = $config['from_name'] !== '' ? $config['from_name'] : ($profile['imprint_name'] ?? '');
        $from      = $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromEmail) : $fromEmail;

        $dsn = sprintf(
            'smtp://%s:%s@%s:%s',
            rawurlencode($user),
            rawurlencode($pass),
            $host,
            $port
        );

        if (strtolower($encryption) !== 'none') {
            $dsn .= '?encryption=' . rawurlencode($encryption);
        }

        $this->mailer = $this->createTransport($dsn);
        $this->twig   = $twig;
        $this->from   = $from;
        $this->audit  = $audit;
        $mainDomain   = (string) (getenv('MAIN_DOMAIN') ?: '');
        $this->baseUrl = $mainDomain !== '' ? 'https://' . $mainDomain : '';
    }

    protected function createTransport(string $dsn): MailerInterface
    {
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    private function baseUrlFromLink(string $link): string
    {
        $parts = parse_url($link);
        if (isset($parts['scheme'], $parts['host'])) {
            $url = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $url .= ':' . $parts['port'];
            }
            return $url;
        }

        return $this->baseUrl;
    }

    /**
     * Send password reset mail with link.
     */
    public function sendPasswordReset(string $to, string $link): void
    {
        $html = $this->twig->render('emails/password_reset.twig', [
            'link'     => $link,
            'base_url' => $this->baseUrlFromLink($link),
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('Passwort zurücksetzen')
            ->html($html);

        $this->mailer->send($email);

        $this->audit?->log('password_reset_mail', ['to' => $to]);
    }

    /**
     * Send double opt-in email with confirmation link.
     */
    public function sendDoubleOptIn(string $to, string $link): void
    {
        $html = $this->twig->render('emails/double_optin.twig', [
            'link'     => $link,
            'base_url' => $this->baseUrlFromLink($link),
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('E-Mail bestätigen')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Send invitation email with registration link.
     */
    public function sendInvitation(string $to, string $name, string $link): void
    {
        $html = $this->twig->render('emails/invitation.twig', [
            'name'     => $name,
            'link'     => $link,
            'base_url' => $this->baseUrlFromLink($link),
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('Einladung zu QuizRace')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Send initial welcome mail with admin link.
     *
     * Returns the rendered HTML (useful for logging/preview).
     *
     * @return string Rendered HTML content of the email
     */
    public function sendWelcome(string $to, string $domain, string $link): string
    {
        $catalogLink = sprintf('https://%s/admin/catalogs', $domain);

        $baseUrl = 'https://' . $domain;
        $html = $this->twig->render('emails/welcome.twig', [
            'domain'       => $domain,
            'link'         => $link,
            'catalog_link' => $catalogLink,
            'base_url'     => $baseUrl,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject('Willkommen bei QuizRace')
            ->html($html);

        $this->mailer->send($email);

        $this->audit?->log('welcome_mail', ['to' => $to, 'domain' => $domain]);

        return $html;
    }

    /**
     * Send contact form message to given recipient.
     */
    public function sendContact(string $to, string $name, string $replyTo, string $message): void
    {
        $html = $this->twig->render('emails/contact.twig', [
            'name'     => $name,
            'email'    => $replyTo,
            'message'  => $message,
            'base_url' => $this->baseUrl,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->replyTo($replyTo)
            ->subject('Kontaktanfrage')
            ->html($html);

        $this->mailer->send($email);

        $copyHtml = $this->twig->render('emails/contact_copy.twig', [
            'name'     => $name,
            'message'  => $message,
            'base_url' => $this->baseUrl,
        ]);

        $copyEmail = (new Email())
            ->from($this->from)
            ->to($replyTo)
            ->subject('Ihre Kontaktanfrage')
            ->html($copyHtml);

        $this->mailer->send($copyEmail);

        $this->audit?->log('contact_mail', ['from' => $replyTo]);
    }
}
