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

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Store the current session id for the given user.
     */
    public function persistSession(int $userId, string $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions(user_id, session_id) VALUES(?, ?) ON CONFLICT DO NOTHING'
        );
        $stmt->execute([$userId, $sessionId]);
    }

    /**
     * Invalidate all sessions associated with the given user id.
     */
    public function invalidateUserSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT session_id FROM user_sessions WHERE user_id=?');
        $stmt->execute([$userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $path = session_save_path();
        if ($path === '') {
            $path = sys_get_temp_dir();
        }

        foreach ($ids as $id) {
            $file = $path . DIRECTORY_SEPARATOR . 'sess_' . $id;
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $del = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id=?');
        $del->execute([$userId]);
    }
}
