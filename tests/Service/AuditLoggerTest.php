<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\AuditLogger;
use PDO;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    public function testLogInsertsRow(): void {
        $pdo = $this->createDatabase();
        $logger = new AuditLogger($pdo);

        $logger->log('test_action', ['foo' => 'bar']);

        $stmt = $pdo->query('SELECT action, context FROM audit_logs');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('test_action', $row['action']);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['foo' => 'bar']),
            (string) $row['context']
        );
    }
}
