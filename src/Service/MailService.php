<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Database;
use App\Service\MailProvider\MailProviderManager;
use App\Service\SettingsService;
use RuntimeException;
use Throwable;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Markup;

/**
 * Simple wrapper around Symfony Mailer using provider abstraction.
 */
class MailService
{
    private Environment $twig;

    private MailProviderManager $providerManager;

    private string $from;

    private ?AuditLogger $audit;

    private string $baseUrl;

    public static function isConfigured(): bool
    {
        return MailProviderManager::isConfiguredStatic();
    }

    public function __construct(Environment $twig, ?MailProviderManager $providerManager = null, ?AuditLogger $audit = null)
    {
        $this->twig = $twig;
        $this->providerManager = $providerManager ?? new MailProviderManager(new SettingsService(Database::connectFromEnv()));
        $this->audit = $audit;

        $status = $this->providerManager->getStatus();
        if (!($status['configured'] ?? false)) {
            $missing = $status['missing'] ?? [];
            if (is_array($missing) && $missing !== []) {
                throw new RuntimeException('Missing SMTP configuration: ' . implode(', ', $missing));
            }

            throw new RuntimeException('Mail provider is not configured.');
        }

        $from = (string) ($status['from_address'] ?? '');
        if ($from === '') {
            $missing = $status['missing'] ?? [];
            if (is_array($missing) && $missing !== []) {
                throw new RuntimeException('Missing SMTP configuration: ' . implode(', ', $missing));
            }

            throw new RuntimeException('Mail provider did not provide a sender address.');
        }

        $this->from = $from;

        $mainDomain = (string) (getenv('MAIN_DOMAIN') ?: '');
        $this->baseUrl = $mainDomain !== '' ? 'https://' . $mainDomain : '';
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

        $this->providerManager->sendMail($email);

        $this->audit?->log('password_reset_mail', ['to' => $to]);
    }

    /**
     * Send double opt-in email with confirmation link.
     */
    /**
     * Send double opt-in email with confirmation link.
     *
     * @param array<string,mixed> $context Additional template values (subject, headline, etc.)
     */
    public function sendDoubleOptIn(string $to, string $link, array $context = []): void
    {
        $subject = isset($context['subject'])
            ? trim((string) $context['subject'])
            : 'E-Mail bestätigen';

        $templateContext = $context + [
            'link' => $link,
            'base_url' => $this->baseUrlFromLink($link),
            'subject' => $subject,
        ];

        $html = $this->twig->render('emails/double_optin.twig', $templateContext);

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->providerManager->sendMail($email);
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

        $this->providerManager->sendMail($email);
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

        $this->providerManager->sendMail($email);

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
        ?array $smtpOverride = null,
        ?string $company = null,
        ?string $subject = null,
        ?array $extraFields = null
    ): void {
        $context = $this->buildContactContext($name, $replyTo, $message, $company);
        $context['extra_fields'] = $extraFields ?? [];
        $templateArray = is_array($templateData) ? $templateData : [];
        $senderName = isset($templateArray['sender_name']) ? trim((string) $templateArray['sender_name']) : null;
        $context['sender_name'] = $senderName ?? '';

        $recipientHtml = $this->renderTemplateString($templateArray['recipient_html'] ?? null, $context);
        $recipientText = $this->renderTemplateString($templateArray['recipient_text'] ?? null, $context);
        $senderHtml = $this->renderTemplateString($templateArray['sender_html'] ?? null, $context);
        $senderText = $this->renderTemplateString($templateArray['sender_text'] ?? null, $context);

        $templateContext = [
            'name'         => $name,
            'email'        => $replyTo,
            'message'      => $message,
            'company'      => $company,
            'extra_fields' => $extraFields ?? [],
            'base_url'     => $this->baseUrl,
        ];

        if ($recipientHtml === null) {
            $recipientHtml = $this->twig->render('emails/contact.twig', $templateContext);
        }
        if ($recipientText === null) {
            $recipientText = $this->buildDefaultRecipientText($name, $replyTo, $message);
        }

        if ($senderHtml === null) {
            $senderHtml = $this->twig->render('emails/contact_copy.twig', $templateContext);
        }
        if ($senderText === null) {
            $senderText = $this->buildDefaultSenderText($name, $message);
        }

        $fromOverride = $this->determineContactFromAddress($fromEmail, $senderName);

        $emailSubject = ($subject !== null && $subject !== '') ? $subject : 'Kontaktanfrage';
        $email = (new Email())
            ->from($fromOverride)
            ->to($to)
            ->replyTo($replyTo)
            ->subject($emailSubject)
            ->html($recipientHtml)
            ->text($recipientText);

        $options = [];
        if ($smtpOverride !== null) {
            $options['smtp_override'] = $smtpOverride;
        }

        $this->providerManager->sendMail($email, $options);

        $copySubject = ($subject !== null && $subject !== '') ? 'Ihre Anfrage: ' . $subject : 'Ihre Kontaktanfrage';
        $copyEmail = (new Email())
            ->from($fromOverride)
            ->to($replyTo)
            ->subject($copySubject)
            ->html($senderHtml)
            ->text($senderText);

        $this->providerManager->sendMail($copyEmail, $options);

        $this->audit?->log('contact_mail', ['from' => $replyTo]);
    }

    private function buildContactContext(string $name, string $replyTo, string $message, ?string $company = null): array
    {
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return [
            'name' => $name,
            'email' => $replyTo,
            'message' => $message,
            'message_plain' => new Markup($message, 'UTF-8'),
            'message_html' => new Markup($safeMessage, 'UTF-8'),
            'company' => $company ?? '',
            'base_url' => $this->baseUrl,
        ];
    }

    private function renderTemplateString(?string $template, array $context): ?string
    {
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

    private function determineContactFromAddress(?string $fromEmail, ?string $senderName): string
    {
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

    private function buildDefaultRecipientText(string $name, string $replyTo, string $message): string
    {
        return sprintf("Kontaktanfrage von %s (%s)\n\n%s", $name, $replyTo, $message);
    }

    private function buildDefaultSenderText(string $name, string $message): string
    {
        return sprintf(
            "Hallo %s,\n\n%s\n\n%s",
            $name,
            'vielen Dank für Ihre Nachricht. Hier ist eine Kopie Ihrer Anfrage:',
            $message
        );
    }
}
