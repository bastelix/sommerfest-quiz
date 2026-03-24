<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Ticket;
use App\Domain\TicketComment;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class TicketService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connectFromEnv();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Ticket[]
     */
    public function listByNamespace(string $namespace, array $filters = []): array
    {
        $where = ['namespace = ?'];
        $params = [$namespace];

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (isset($filters['priority']) && is_string($filters['priority']) && $filters['priority'] !== '') {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        if (isset($filters['assignee']) && is_string($filters['assignee']) && $filters['assignee'] !== '') {
            $where[] = 'assignee = ?';
            $params[] = $filters['assignee'];
        }
        if (isset($filters['referenceType']) && is_string($filters['referenceType']) && $filters['referenceType'] !== '') {
            $where[] = 'reference_type = ?';
            $params[] = $filters['referenceType'];
        }
        if (isset($filters['referenceId']) && is_numeric($filters['referenceId'])) {
            $where[] = 'reference_id = ?';
            $params[] = (int) $filters['referenceId'];
        }

        $sql = 'SELECT * FROM tickets WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateTicket'], $rows);
    }

    public function getById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateTicket($row);
    }

    /**
     * @param list<string> $labels
     */
    public function create(
        string $namespace,
        string $title,
        string $description,
        string $type,
        string $priority,
        ?string $referenceType,
        ?int $referenceId,
        ?string $assignee,
        array $labels,
        ?string $dueDate,
        ?string $createdBy,
    ): Ticket {
        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException('Ticket title must not be empty.');
        }
        if (!Ticket::isValidType($type)) {
            throw new RuntimeException('Invalid ticket type: ' . $type);
        }
        if (!Ticket::isValidPriority($priority)) {
            throw new RuntimeException('Invalid ticket priority: ' . $priority);
        }
        if (!Ticket::isValidReferenceType($referenceType)) {
            throw new RuntimeException('Invalid reference type: ' . $referenceType);
        }
        if (($referenceType !== null) !== ($referenceId !== null)) {
            throw new RuntimeException('reference_type and reference_id must both be set or both be null.');
        }

        $parsedDueDate = null;
        if ($dueDate !== null && $dueDate !== '') {
            try {
                $parsedDueDate = new DateTimeImmutable($dueDate);
            } catch (\Throwable $e) {
                throw new RuntimeException('Invalid due_date format: ' . $dueDate);
            }
        }

        $labelsJson = json_encode(array_values($labels), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (namespace, title, description, status, priority, type, reference_type, reference_id, assignee, labels, due_date, created_by) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $namespace,
            $title,
            $description,
            Ticket::STATUS_OPEN,
            $priority,
            $type,
            $referenceType,
            $referenceId,
            $assignee,
            $labelsJson,
            $parsedDueDate?->format('Y-m-d H:i:s'),
            $createdBy,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->getById($id) ?? throw new RuntimeException('Failed to retrieve created ticket.');
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $id, array $fields): Ticket
    {
        $existing = $this->getById($id);
        if ($existing === null) {
            throw new RuntimeException('Ticket not found: ' . $id);
        }

        $sets = [];
        $params = [];

        if (array_key_exists('title', $fields) && is_string($fields['title'])) {
            $title = trim($fields['title']);
            if ($title === '') {
                throw new RuntimeException('Ticket title must not be empty.');
            }
            $sets[] = 'title = ?';
            $params[] = $title;
        }
        if (array_key_exists('description', $fields) && is_string($fields['description'])) {
            $sets[] = 'description = ?';
            $params[] = $fields['description'];
        }
        if (array_key_exists('priority', $fields) && is_string($fields['priority'])) {
            if (!Ticket::isValidPriority($fields['priority'])) {
                throw new RuntimeException('Invalid ticket priority: ' . $fields['priority']);
            }
            $sets[] = 'priority = ?';
            $params[] = $fields['priority'];
        }
        if (array_key_exists('type', $fields) && is_string($fields['type'])) {
            if (!Ticket::isValidType($fields['type'])) {
                throw new RuntimeException('Invalid ticket type: ' . $fields['type']);
            }
            $sets[] = 'type = ?';
            $params[] = $fields['type'];
        }
        if (array_key_exists('assignee', $fields)) {
            $sets[] = 'assignee = ?';
            $params[] = $fields['assignee'];
        }
        if (array_key_exists('labels', $fields) && is_array($fields['labels'])) {
            $sets[] = 'labels = ?';
            $params[] = json_encode(array_values($fields['labels']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('dueDate', $fields)) {
            if ($fields['dueDate'] === null) {
                $sets[] = 'due_date = NULL';
            } elseif (is_string($fields['dueDate'])) {
                try {
                    $parsed = new DateTimeImmutable($fields['dueDate']);
                    $sets[] = 'due_date = ?';
                    $params[] = $parsed->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    throw new RuntimeException('Invalid due_date format: ' . $fields['dueDate']);
                }
            }
        }
        if (array_key_exists('referenceType', $fields) && array_key_exists('referenceId', $fields)) {
            $refType = $fields['referenceType'];
            $refId = $fields['referenceId'];
            if ($refType === null && $refId === null) {
                $sets[] = 'reference_type = NULL';
                $sets[] = 'reference_id = NULL';
            } elseif (is_string($refType) && is_numeric($refId)) {
                if (!Ticket::isValidReferenceType($refType)) {
                    throw new RuntimeException('Invalid reference type: ' . $refType);
                }
                $sets[] = 'reference_type = ?';
                $params[] = $refType;
                $sets[] = 'reference_id = ?';
                $params[] = (int) $refId;
            }
        }

        if ($sets === []) {
            return $existing;
        }

        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        $stmt = $this->pdo->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);

        return $this->getById($id) ?? throw new RuntimeException('Ticket not found after update.');
    }

    public function transition(int $id, string $newStatus): Ticket
    {
        $existing = $this->getById($id);
        if ($existing === null) {
            throw new RuntimeException('Ticket not found: ' . $id);
        }

        if (!Ticket::isValidStatus($newStatus)) {
            throw new RuntimeException('Invalid status: ' . $newStatus);
        }

        if (!Ticket::canTransition($existing->getStatus(), $newStatus)) {
            throw new RuntimeException(
                'Cannot transition from "' . $existing->getStatus() . '" to "' . $newStatus . '".'
            );
        }

        $stmt = $this->pdo->prepare('UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$newStatus, $id]);

        return $this->getById($id) ?? throw new RuntimeException('Ticket not found after transition.');
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function addComment(int $ticketId, string $author, string $body): TicketComment
    {
        $author = trim($author);
        $body = trim($body);
        if ($author === '' || $body === '') {
            throw new RuntimeException('Comment author and body must not be empty.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ticket_comments (ticket_id, author, body) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ticketId, $author, $body]);

        $id = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('SELECT * FROM ticket_comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Failed to retrieve created comment.');
        }

        return $this->hydrateComment($row);
    }

    /**
     * @return TicketComment[]
     */
    public function listComments(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ticket_comments WHERE ticket_id = ? ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$ticketId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateComment'], $rows);
    }

    public function deleteComment(int $commentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ticket_comments WHERE id = ?');
        $stmt->execute([$commentId]);
    }

    private function hydrateTicket(array $row): Ticket
    {
        $labels = [];
        if (isset($row['labels']) && $row['labels'] !== '' && $row['labels'] !== '[]') {
            $decoded = json_decode($row['labels'], true);
            if (is_array($decoded)) {
                $labels = array_values($decoded);
            }
        }

        return new Ticket(
            (int) $row['id'],
            (string) $row['namespace'],
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['status'],
            (string) $row['priority'],
            (string) $row['type'],
            isset($row['reference_type']) && $row['reference_type'] !== '' ? (string) $row['reference_type'] : null,
            isset($row['reference_id']) ? (int) $row['reference_id'] : null,
            isset($row['assignee']) && $row['assignee'] !== '' ? (string) $row['assignee'] : null,
            $labels,
            isset($row['due_date']) ? new DateTimeImmutable($row['due_date']) : null,
            isset($row['created_by']) && $row['created_by'] !== '' ? (string) $row['created_by'] : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
        );
    }

    private function hydrateComment(array $row): TicketComment
    {
        return new TicketComment(
            (int) $row['id'],
            (int) $row['ticket_id'],
            (string) $row['author'],
            (string) $row['body'],
            new DateTimeImmutable($row['created_at']),
        );
    }
}
