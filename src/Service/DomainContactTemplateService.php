<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\DomainNameHelper;
use PDO;
use PDOException;

/**
 * Provides persistence for domain specific contact mail templates.
 */
class DomainContactTemplateService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetch a stored template configuration for the given domain.
     *
     * @return array{
     *     domain:string,
     *     sender_name:?string,
     *     recipient_html:?string,
     *     recipient_text:?string,
     *     sender_html:?string,
     *     sender_text:?string
     * }|null
     */
    public function get(string $domain): ?array {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT domain, sender_name, recipient_html, recipient_text, sender_html, sender_text
             FROM domain_contact_templates WHERE domain = ?'
        );
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'domain' => $normalized,
            'sender_name' => array_key_exists('sender_name', $row)
                && $row['sender_name'] !== null
                ? (string) $row['sender_name']
                : null,
            'recipient_html' => array_key_exists('recipient_html', $row)
                && $row['recipient_html'] !== null
                ? (string) $row['recipient_html']
                : null,
            'recipient_text' => array_key_exists('recipient_text', $row)
                && $row['recipient_text'] !== null
                ? (string) $row['recipient_text']
                : null,
            'sender_html' => array_key_exists('sender_html', $row)
                && $row['sender_html'] !== null
                ? (string) $row['sender_html']
                : null,
            'sender_text' => array_key_exists('sender_text', $row)
                && $row['sender_text'] !== null
                ? (string) $row['sender_text']
                : null,
        ];
    }

    /**
     * Convenience wrapper to load template data for a request host.
     *
     * @return array{
     *     domain:string,
     *     sender_name:?string,
     *     recipient_html:?string,
     *     recipient_text:?string,
     *     sender_html:?string,
     *     sender_text:?string
     * }|null
     */
    public function getForHost(string $host): ?array {
        return $this->get($host);
    }

    /**
     * Persist template content for the given domain.
     *
     * @param array{
     *     sender_name:?string,
     *     recipient_html:?string,
     *     recipient_text:?string,
     *     sender_html:?string,
     *     sender_text:?string
     * } $data
     */
    public function save(string $domain, array $data): void {
        $normalized = $this->normalizeDomain($domain);
        if ($normalized === '') {
            throw new PDOException('Invalid domain supplied');
        }

        $payload = [
            'domain' => $normalized,
            'sender_name' => $this->normalizeNullable($data['sender_name'] ?? null),
            'recipient_html' => $this->normalizeNullable($data['recipient_html'] ?? null, false),
            'recipient_text' => $this->normalizeNullable($data['recipient_text'] ?? null, false),
            'sender_html' => $this->normalizeNullable($data['sender_html'] ?? null, false),
            'sender_text' => $this->normalizeNullable($data['sender_text'] ?? null, false),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO domain_contact_templates(domain, sender_name, recipient_html, recipient_text, '
            . 'sender_html, sender_text)
             VALUES(:domain, :sender_name, :recipient_html, :recipient_text, :sender_html, :sender_text)
             ON CONFLICT(domain) DO UPDATE SET
                 sender_name = excluded.sender_name,
                 recipient_html = excluded.recipient_html,
                 recipient_text = excluded.recipient_text,
                 sender_html = excluded.sender_html,
                 sender_text = excluded.sender_text,
                 updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute($payload);
    }

    private function normalizeDomain(string $domain): string {
        return DomainNameHelper::normalize($domain);
    }

    private function normalizeNullable(?string $value, bool $trimWhitespace = true): ?string {
        if ($value === null) {
            return null;
        }

        $value = $trimWhitespace ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}
