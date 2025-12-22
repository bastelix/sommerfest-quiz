<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

use function json_decode;
use function json_encode;
use function strtolower;
use function trim;

final class PageAiJobRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(
        string $jobId,
        string $namespace,
        string $slug,
        array $payload
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO page_ai_jobs (id, namespace, slug, status, payload, created_at, updated_at)'
            . ' VALUES (:id, :namespace, :slug, :status, :payload, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        try {
            $stmt->execute([
                'id' => $jobId,
                'namespace' => $namespace,
                'slug' => $slug,
                'status' => 'pending',
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to create page AI job.', 0, $exception);
        }
    }

    public function find(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, namespace, slug, status, result_html, error_code, error_message, payload, updated_at'
            . ' FROM page_ai_jobs WHERE id = ?'
        );
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $payload = [];
        $payloadRaw = $row['payload'] ?? '';
        if (is_string($payloadRaw) && $payloadRaw !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'id' => (string) $row['id'],
            'namespace' => (string) $row['namespace'],
            'slug' => (string) $row['slug'],
            'status' => strtolower((string) $row['status']),
            'result_html' => is_string($row['result_html'] ?? null) ? (string) $row['result_html'] : null,
            'error_code' => is_string($row['error_code'] ?? null) ? (string) $row['error_code'] : null,
            'error_message' => is_string($row['error_message'] ?? null) ? (string) $row['error_message'] : null,
            'payload' => $payload,
            'updated_at' => $this->parseTimestamp($row['updated_at'] ?? null),
        ];
    }

    public function markDone(string $jobId, string $html): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE page_ai_jobs'
            . ' SET status = :status, result_html = :html, error_code = NULL, error_message = NULL, updated_at = CURRENT_TIMESTAMP'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'done',
            'html' => $html,
            'id' => $jobId,
        ]);
    }

    public function markFailed(string $jobId, string $code, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE page_ai_jobs'
            . ' SET status = :status, error_code = :code, error_message = :message, updated_at = CURRENT_TIMESTAMP'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'failed',
            'code' => $code,
            'message' => $message,
            'id' => $jobId,
        ]);
    }

    public function markProcessing(string $jobId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE page_ai_jobs'
            . ' SET status = :status, updated_at = CURRENT_TIMESTAMP'
            . ' WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'processing',
            'id' => $jobId,
        ]);
    }

    private function parseTimestamp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $timestamp = new DateTimeImmutable($value);
        } catch (\Throwable) {
            return $value;
        }

        return $timestamp->format(DATE_ATOM);
    }
}
