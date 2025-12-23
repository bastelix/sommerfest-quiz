<?php

declare(strict_types=1);

namespace App\Repository;

use App\Infrastructure\Database;
use PDO;
use RuntimeException;

use function bin2hex;
use function is_array;
use function random_bytes;
use function trim;

final class PageAiJobRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    public function createJob(
        string $namespace,
        string $slug,
        string $title,
        string $theme,
        string $colorScheme,
        string $problem,
        ?string $promptTemplate
    ): string {
        $jobId = bin2hex(random_bytes(16));

        $stmt = $this->pdo->prepare(
            'INSERT INTO page_ai_jobs (job_id, namespace, slug, title, theme, color_scheme, problem, prompt_template, status) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $jobId,
            trim($namespace),
            trim($slug),
            trim($title),
            trim($theme),
            trim($colorScheme),
            trim($problem),
            $promptTemplate,
            self::STATUS_PENDING,
        ]);

        return $jobId;
    }

    /**
     * @return array{
     *     job_id:string,
     *     namespace:string,
     *     slug:string,
     *     title:string,
     *     theme:string,
     *     color_scheme:string,
     *     problem:string,
     *     prompt_template:?string,
     *     status:string,
     *     html:?string,
     *     error_code:?string,
     *     error_message:?string
     * }|null
     */
    public function getJob(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT job_id, namespace, slug, title, theme, color_scheme, problem, prompt_template, status, html, error_code, error_message '
            . 'FROM page_ai_jobs WHERE job_id = ? LIMIT 1'
        );
        $stmt->execute([trim($jobId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'job_id' => (string) $row['job_id'],
            'namespace' => (string) $row['namespace'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'theme' => (string) $row['theme'],
            'color_scheme' => (string) $row['color_scheme'],
            'problem' => (string) $row['problem'],
            'prompt_template' => $row['prompt_template'] !== null ? (string) $row['prompt_template'] : null,
            'status' => (string) $row['status'],
            'html' => $row['html'] !== null ? (string) $row['html'] : null,
            'error_code' => $row['error_code'] !== null ? (string) $row['error_code'] : null,
            'error_message' => $row['error_message'] !== null ? (string) $row['error_message'] : null,
        ];
    }

    /**
     * @return array{
     *     job_id:string,
     *     namespace:string,
     *     slug:string,
     *     title:string,
     *     theme:string,
     *     color_scheme:string,
     *     problem:string,
     *     prompt_template:?string
     * }|null
     */
    public function getPendingJob(string $jobId): ?array
    {
        $job = $this->getJob($jobId);
        if ($job === null || $job['status'] !== self::STATUS_PENDING) {
            return null;
        }

        return [
            'job_id' => $job['job_id'],
            'namespace' => $job['namespace'],
            'slug' => $job['slug'],
            'title' => $job['title'],
            'theme' => $job['theme'],
            'color_scheme' => $job['color_scheme'],
            'problem' => $job['problem'],
            'prompt_template' => $job['prompt_template'],
        ];
    }

    public function markDone(string $jobId, string $html): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE page_ai_jobs SET status = ?, html = ?, error_code = NULL, error_message = NULL, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE job_id = ?'
        );
        $stmt->execute([self::STATUS_DONE, $html, trim($jobId)]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Failed to update page AI job status.');
        }
    }

    public function markFailed(string $jobId, string $errorCode, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE page_ai_jobs SET status = ?, error_code = ?, error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE job_id = ?'
        );
        $stmt->execute([self::STATUS_FAILED, trim($errorCode), trim($errorMessage), trim($jobId)]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Failed to update page AI job status.');
        }
    }
}
