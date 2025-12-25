<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Service\PageService;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Repository for storing and retrieving mail provider configurations.
 */
class MailProviderRepository
{
    private const CIPHER = 'aes-256-gcm';

    private PDO $pdo;

    private string $key;

    private int $ivLength;

    private string $namespace;

    public function __construct(PDO $pdo, ?string $secret = null)
    {
        $secret ??= (string) (getenv('MAIL_PROVIDER_SECRET') ?: getenv('PASSWORD_RESET_SECRET') ?: '');
        if ($secret === '') {
            throw new RuntimeException('MAIL_PROVIDER_SECRET or PASSWORD_RESET_SECRET must be configured.');
        }

        $binaryKey = hash('sha256', $secret, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            throw new RuntimeException('Unable to determine IV length for mail provider encryption.');
        }

        $this->pdo = $pdo;
        $this->key = $binaryKey;
        $this->ivLength = $ivLength;
        $this->namespace = PageService::DEFAULT_NAMESPACE;
    }

    /**
     * Retrieve all stored providers.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(?string $namespace = null): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mail_providers WHERE namespace = :namespace ORDER BY provider_name');
        $stmt->execute(['namespace' => $this->normalizeNamespace($namespace)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapRow'], $rows ?: []);
    }

    /**
     * Retrieve provider by name.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $provider, ?string $namespace = null): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mail_providers WHERE provider_name = :name AND namespace = :namespace'
        );
        $stmt->execute([
            'name' => $provider,
            'namespace' => $this->normalizeNamespace($namespace),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * Retrieve the currently active provider configuration.
     *
     * @return array<string,mixed>|null
     */
    public function findActive(?string $namespace = null): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mail_providers WHERE namespace = :namespace AND active = TRUE LIMIT 1'
        );
        $stmt->execute(['namespace' => $this->normalizeNamespace($namespace)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * Persist configuration for the given provider.
     *
     * @param array<string,mixed> $data
     */
    public function save(string $provider, array $data, ?string $namespace = null): void
    {
        $settings = $data['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $normalizedNamespace = $this->normalizeNamespace($namespace);

        $payload = [
            'provider_name' => $provider,
            'namespace' => $normalizedNamespace,
            'api_key' => $this->encrypt($this->normalizeString($data['api_key'] ?? null)),
            'list_id' => $this->normalizeString($data['list_id'] ?? null),
            'smtp_host' => $this->normalizeString($data['smtp_host'] ?? null),
            'smtp_user' => $this->encrypt($this->normalizeString($data['smtp_user'] ?? null)),
            'smtp_pass' => $this->encrypt($this->normalizeString($data['smtp_pass'] ?? null)),
            'smtp_port' => $this->normalizePort($data['smtp_port'] ?? null),
            'smtp_encryption' => $this->normalizeString($data['smtp_encryption'] ?? null),
            'active' => (bool) ($data['active'] ?? false),
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        if ($payload['settings'] === false) {
            throw new RuntimeException('Failed to encode mail provider settings as JSON.');
        }

        $shouldActivate = (bool) $payload['active'];

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mail_providers '
                . '(provider_name, namespace, api_key, list_id, smtp_host, smtp_user, smtp_pass, smtp_port, smtp_encryption, active, settings) '
                . 'VALUES (:provider_name, :namespace, :api_key, :list_id, :smtp_host, :smtp_user, :smtp_pass, :smtp_port, :smtp_encryption, :active, :settings) '
                . 'ON CONFLICT (namespace, provider_name) DO UPDATE SET '
                . 'api_key = EXCLUDED.api_key, '
                . 'list_id = EXCLUDED.list_id, '
                . 'smtp_host = EXCLUDED.smtp_host, '
                . 'smtp_user = EXCLUDED.smtp_user, '
                . 'smtp_pass = EXCLUDED.smtp_pass, '
                . 'smtp_port = EXCLUDED.smtp_port, '
                . 'smtp_encryption = EXCLUDED.smtp_encryption, '
                . 'active = EXCLUDED.active, '
                . 'settings = EXCLUDED.settings'
            );
            $stmt->execute($payload);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Failed to persist mail provider configuration.', 0, $exception);
        }

        if ($shouldActivate) {
            $this->activate($provider);
        } else {
            $this->deactivate($provider);
        }
    }

    public function activate(string $provider, ?string $namespace = null): void
    {
        $normalizedNamespace = $this->normalizeNamespace($namespace);
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('UPDATE mail_providers SET active = FALSE WHERE namespace = :namespace');
            $stmt->execute(['namespace' => $normalizedNamespace]);
            $stmt = $this->pdo->prepare(
                'UPDATE mail_providers SET active = TRUE WHERE provider_name = :name AND namespace = :namespace'
            );
            $stmt->execute(['name' => $provider, 'namespace' => $normalizedNamespace]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new RuntimeException('Failed to activate mail provider.', 0, $exception);
        }
    }

    public function deactivate(string $provider, ?string $namespace = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mail_providers SET active = FALSE WHERE provider_name = :name AND namespace = :namespace'
        );
        $stmt->execute([
            'name' => $provider,
            'namespace' => $this->normalizeNamespace($namespace),
        ]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapRow(array $row): array
    {
        $settings = [];
        if (array_key_exists('settings', $row) && $row['settings'] !== null) {
            $decoded = json_decode((string) $row['settings'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'namespace' => $this->normalizeNamespace($row['namespace'] ?? null),
            'api_key' => $this->decrypt($row['api_key'] ?? null),
            'list_id' => $this->normalizeString($row['list_id'] ?? null),
            'smtp_host' => $this->normalizeString($row['smtp_host'] ?? null),
            'smtp_user' => $this->decrypt($row['smtp_user'] ?? null),
            'smtp_pass' => $this->decrypt($row['smtp_pass'] ?? null),
            'smtp_port' => $this->normalizePort($row['smtp_port'] ?? null),
            'smtp_encryption' => $this->normalizeString($row['smtp_encryption'] ?? null),
            'active' => (bool) ($row['active'] ?? false),
            'settings' => $settings,
        ];
    }

    private function normalizeNamespace(mixed $namespace): string
    {
        $candidate = is_string($namespace) ? strtolower(trim($namespace)) : '';

        return $candidate !== '' ? $candidate : $this->namespace;
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePort($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $iv = random_bytes($this->ivLength);
        $tag = '';
        $cipher = openssl_encrypt($value, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Failed to encrypt mail provider secret.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    private function decrypt($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = base64_decode((string) $value, true);
        if ($decoded === false) {
            return null;
        }

        if (strlen($decoded) <= $this->ivLength + 16) {
            return null;
        }

        $iv = substr($decoded, 0, $this->ivLength);
        $tag = substr($decoded, $this->ivLength, 16);
        $cipher = substr($decoded, $this->ivLength + 16);
        if ($iv === '' || $tag === '') {
            return null;
        }

        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            return null;
        }

        return $plain;
    }
}
