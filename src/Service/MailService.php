<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Service\AuditLogger;
use App\Service\TenantService;
use RuntimeException;
use Throwable;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Markup;

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

    private static function loadEnvConfig(): array {
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';
        $env = [];

        if (is_readable($envFile)) {
            $env = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
        }

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

    public static function isConfigured(): bool {
        $config = self::loadEnvConfig();

        if ($config['mailer_dsn'] !== '') {
            return true;
        }

        return $config['host'] !== '' && $config['user'] !== '' && $config['pass'] !== '';
    }

    public function __construct(Environment $twig, ?AuditLogger $audit = null) {
        $config = self::loadEnvConfig();

        $mailerDsn  = $config['mailer_dsn'];
        $host       = $config['host'];
        $user       = $config['user'];
        $pass       = $config['pass'];
        $port       = $config['port'];
        $encryption = $config['encryption'];

        if ($mailerDsn === '' && ($host === '' || $user === '' || $pass === '')) {
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

        if ($fromEmail === '') {
            throw new RuntimeException('Missing SMTP configuration: SMTP_FROM');
        }

        if ($mailerDsn !== '') {
            $dsn = $mailerDsn;
        } else {
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
        }

        $this->mailer = $this->createTransport($dsn);
        $this->twig   = $twig;
        $this->from   = $from;
        $this->audit  = $audit;
        $mainDomain   = (string) (getenv('MAIN_DOMAIN') ?: '');
        $this->baseUrl = $mainDomain !== '' ? 'https://' . $mainDomain : '';
    }

    protected function createTransport(string $dsn): MailerInterface {
        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    private function baseUrlFromLink(string $link): string {
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
    public function sendPasswordReset(string $to, string $link): void {
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
    public function sendDoubleOptIn(string $to, string $link): void {
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
    public function sendInvitation(string $to, string $name, string $link): void {
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
    public function sendWelcome(string $to, string $domain, string $link): string {
        $adminLink = sprintf('https://%s/admin', $domain);

        $baseUrl = 'https://' . $domain;
        $html = $this->twig->render('emails/welcome.twig', [
            'domain'     => $domain,
            'link'       => $link,
            'admin_link' => $adminLink,
            'base_url'   => $baseUrl,
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
    public function sendContact(
        string $to,
        string $name,
        string $replyTo,
        string $message,
        ?array $templateData = null,
        ?string $fromEmail = null,
        ?array $smtpOverride = null
    ): void {
        $activeMailer = $this->mailer;
        if (is_array($smtpOverride)) {
            $overrideMailer = $this->buildOverrideMailer($smtpOverride);
            if ($overrideMailer !== null) {
                $activeMailer = $overrideMailer;
            }
        }

        $context = $this->buildContactContext($name, $replyTo, $message);
        $templateArray = is_array($templateData) ? $templateData : [];
        $senderName = isset($templateArray['sender_name']) ? trim((string) $templateArray['sender_name']) : null;
        $context['sender_name'] = $senderName ?? '';

        $recipientHtml = $this->renderTemplateString($templateArray['recipient_html'] ?? null, $context);
        $recipientText = $this->renderTemplateString($templateArray['recipient_text'] ?? null, $context);
        $senderHtml = $this->renderTemplateString($templateArray['sender_html'] ?? null, $context);
        $senderText = $this->renderTemplateString($templateArray['sender_text'] ?? null, $context);

        if ($recipientHtml === null) {
            $recipientHtml = $this->twig->render('emails/contact.twig', [
                'name'     => $name,
                'email'    => $replyTo,
                'message'  => $message,
                'base_url' => $this->baseUrl,
            ]);
        }
        if ($recipientText === null) {
            $recipientText = $this->buildDefaultRecipientText($name, $replyTo, $message);
        }

        if ($senderHtml === null) {
            $senderHtml = $this->twig->render('emails/contact_copy.twig', [
                'name'     => $name,
                'message'  => $message,
                'base_url' => $this->baseUrl,
            ]);
        }
        if ($senderText === null) {
            $senderText = $this->buildDefaultSenderText($name, $message);
        }

        $fromOverride = $this->determineContactFromAddress($fromEmail, $senderName);

        $email = (new Email())
            ->from($fromOverride)
            ->to($to)
            ->replyTo($replyTo)
            ->subject('Kontaktanfrage')
            ->html($recipientHtml)
            ->text($recipientText);

        $activeMailer->send($email);

        $copyEmail = (new Email())
            ->from($fromOverride)
            ->to($replyTo)
            ->subject('Ihre Kontaktanfrage')
            ->html($senderHtml)
            ->text($senderText);

        $activeMailer->send($copyEmail);

        $this->audit?->log('contact_mail', ['from' => $replyTo]);
    }

    private function buildContactContext(string $name, string $replyTo, string $message): array {
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return [
            'name' => $name,
            'email' => $replyTo,
            'message' => $message,
            'message_plain' => new Markup($message, 'UTF-8'),
            'message_html' => new Markup($safeMessage, 'UTF-8'),
            'base_url' => $this->baseUrl,
        ];
    }

    private function renderTemplateString(?string $template, array $context): ?string {
        if ($template === null) {
            return null;
        }

        $template = trim($template);
        if ($template === '') {
            return null;
        }

        try {
            return $this->twig->createTemplate($template)->render($context);
        } catch (Throwable $e) {
            error_log('Failed to render contact template: ' . $e->getMessage());
        }

        return null;
    }

    private function determineContactFromAddress(?string $fromEmail, ?string $senderName): string {
        $email = $fromEmail !== null ? trim($fromEmail) : '';
        if ($email === '') {
            return $this->from;
        }

        $name = $senderName !== null ? trim($senderName) : '';
        if ($name === '') {
            return $email;
        }

        return sprintf('%s <%s>', $name, $email);
    }

    private function buildDefaultRecipientText(string $name, string $replyTo, string $message): string {
        return sprintf("Kontaktanfrage von %s (%s)\n\n%s", $name, $replyTo, $message);
    }

    private function buildDefaultSenderText(string $name, string $message): string {
        return sprintf("Hallo %s,\n\n%s\n\n%s", $name, 'vielen Dank für Ihre Nachricht. Hier ist eine Kopie Ihrer Anfrage:', $message);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function buildOverrideMailer(array $config): ?MailerInterface {
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
