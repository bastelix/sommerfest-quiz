<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Simple key/value settings storage.
 */
class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Retrieve all settings as an associative array.
     *
     * @return array<string,string>
     */
    public function getAll(): array {
        $stmt = $this->pdo->query('SELECT key, value FROM settings');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    /**
     * Get a single setting value or default if none exists.
     */
    public function get(string $key, ?string $default = null): ?string {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key=?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null) {
            return $default;
        }
        return (string)$val;
    }

    /**
     * Persist one or more settings.
     *
     * @param array<string,string> $data
     */
    public function save(array $data): void {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings(key,value) VALUES(?,?) '
            . 'ON CONFLICT(key) DO UPDATE SET value=excluded.value'
        );
        foreach ($data as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $this->pdo->commit();
    }
}
