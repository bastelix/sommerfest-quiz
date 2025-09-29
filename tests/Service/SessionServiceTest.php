<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\SessionService;
use Tests\TestCase;

class SessionServiceTest extends TestCase
{
    public function testInvalidateRemovesSessions(): void {
        $pdo = $this->createDatabase();
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_sessions(user_id INTEGER NOT NULL, session_id TEXT PRIMARY KEY)');

        $pdo->exec("INSERT INTO user_sessions(user_id, session_id) VALUES (1,'abc'),(1,'def'),(2,'zzz')");

        $path = sys_get_temp_dir() . '/sess_' . uniqid();
        mkdir($path);
        $old = session_save_path();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_save_path($path);
        ini_set('session.save_path', $path);
        file_put_contents($path . '/sess_abc', '');
        file_put_contents($path . '/sess_def', '');
        file_put_contents($path . '/sess_zzz', '');

        $service = new SessionService($pdo);
        $service->invalidateUserSessions(1);

        $this->assertFileDoesNotExist($path . '/sess_abc');
        $this->assertFileDoesNotExist($path . '/sess_def');
        $this->assertFileExists($path . '/sess_zzz');

        $count1 = $pdo->query('SELECT COUNT(*) FROM user_sessions WHERE user_id=1')->fetchColumn();
        $count2 = $pdo->query('SELECT COUNT(*) FROM user_sessions WHERE user_id=2')->fetchColumn();
        $this->assertSame('0', (string)$count1);
        $this->assertSame('1', (string)$count2);

        @unlink($path . '/sess_zzz');
        @rmdir($path);
        session_save_path($old);
    }
}
