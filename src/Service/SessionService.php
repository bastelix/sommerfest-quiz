<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Manage persisted session identifiers for users.
 */
class SessionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * @param list<array{namespace:string,is_default:bool}> $namespaces
     */
    public function resolveActiveNamespace(array $namespaces, ?string $preferred = null): string {
        $preferred = $this->normalizeNamespace($preferred);
        $fallback = null;
        $default = null;

        foreach ($namespaces as $entry) {
            $namespace = $this->normalizeNamespace((string) $entry['namespace']);
            if ($namespace === null) {
                continue;
            }

            $fallback ??= $namespace;

            if ($preferred !== null && $namespace === $preferred) {
                return $namespace;
            }

            if ($default === null && !empty($entry['is_default'])) {
                $default = $namespace;
            }
        }

        if ($default !== null) {
            return $default;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        return PageService::DEFAULT_NAMESPACE;
    }

    /**
     * Store the current session id for the given user.
     */
    public function persistSession(int $userId, string $sessionId): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions(user_id, session_id) VALUES(?, ?) ON CONFLICT DO NOTHING'
        );
        $stmt->execute([$userId, $sessionId]);
    }

    /**
     * Invalidate all sessions associated with the given user id.
     */
    public function invalidateUserSessions(int $userId): void {
        $stmt = $this->pdo->prepare('SELECT session_id FROM user_sessions WHERE user_id=?');
        $stmt->execute([$userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $path = session_save_path();
        if ($path === '' || str_contains($path, ';')) {
            $parts = array_filter(explode(';', $path));
            $path = end($parts) ?: '';
        }
        if ($path === '') {
            $path = sys_get_temp_dir();
        }

        foreach ($ids as $id) {
            $patterns = [
                $path . DIRECTORY_SEPARATOR . 'sess_' . $id,
                $path . DIRECTORY_SEPARATOR . 'sess_*' . DIRECTORY_SEPARATOR . 'sess_' . $id,
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sess_' . $id,
            ];
            foreach ($patterns as $pattern) {
                foreach (glob($pattern) ?: [] as $file) {
                    @unlink($file);
                }
            }
        }

        $del = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id=?');
        $del->execute([$userId]);
    }

    private function normalizeNamespace(?string $candidate): ?string {
        if ($candidate === null) {
            return null;
        }

        $normalized = strtolower(trim($candidate));
        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }
}
