<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use JsonSerializable;

final class Ticket implements JsonSerializable
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const TYPE_BUG = 'bug';
    public const TYPE_TASK = 'task';
    public const TYPE_REVIEW = 'review';
    public const TYPE_IMPROVEMENT = 'improvement';

    public const REFERENCE_WIKI_ARTICLE = 'wiki_article';
    public const REFERENCE_PAGE = 'page';

    private const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    private const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    private const VALID_TYPES = [
        self::TYPE_BUG,
        self::TYPE_TASK,
        self::TYPE_REVIEW,
        self::TYPE_IMPROVEMENT,
    ];

    private const VALID_REFERENCE_TYPES = [
        self::REFERENCE_WIKI_ARTICLE,
        self::REFERENCE_PAGE,
    ];

    private const ALLOWED_TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_RESOLVED, self::STATUS_OPEN],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED, self::STATUS_IN_PROGRESS],
        self::STATUS_CLOSED => [self::STATUS_OPEN],
    ];

    /**
     * @param list<string> $labels
     */
    public function __construct(
        private readonly int $id,
        private readonly string $namespace,
        private readonly string $title,
        private readonly string $description,
        private readonly string $status,
        private readonly string $priority,
        private readonly string $type,
        private readonly ?string $referenceType,
        private readonly ?int $referenceId,
        private readonly ?string $assignee,
        private readonly array $labels,
        private readonly ?DateTimeImmutable $dueDate,
        private readonly ?string $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getDueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->id,
            $this->namespace,
            $this->title,
            $this->description,
            $status,
            $this->priority,
            $this->type,
            $this->referenceType,
            $this->referenceId,
            $this->assignee,
            $this->labels,
            $this->dueDate,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }

    public static function isValidPriority(string $priority): bool
    {
        return in_array($priority, self::VALID_PRIORITIES, true);
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }

    public static function isValidReferenceType(?string $referenceType): bool
    {
        return $referenceType === null || in_array($referenceType, self::VALID_REFERENCE_TYPES, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return isset(self::ALLOWED_TRANSITIONS[$from])
            && in_array($to, self::ALLOWED_TRANSITIONS[$from], true);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'namespace' => $this->namespace,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'type' => $this->type,
            'referenceType' => $this->referenceType,
            'referenceId' => $this->referenceId,
            'assignee' => $this->assignee,
            'labels' => $this->labels,
            'dueDate' => $this->dueDate?->format(DateTimeImmutable::ATOM),
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
