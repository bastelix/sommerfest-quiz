<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Represents a newsletter campaign linked to landing news entries.
 */
class NewsletterCampaign
{
    private int $id;

    private string $namespace;

    private string $name;

    /**
     * @var int[]
     */
    private array $newsIds;

    private ?string $templateId;

    private ?string $audienceId;

    private string $status;

    private ?string $providerCampaignId;

    private ?string $providerMessageId;

    private ?DateTimeImmutable $scheduledFor;

    private ?DateTimeImmutable $sentAt;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    /**
     * @param int[] $newsIds
     */
    public function __construct(
        int $id,
        string $namespace,
        string $name,
        array $newsIds,
        ?string $templateId,
        ?string $audienceId,
        string $status,
        ?string $providerCampaignId,
        ?string $providerMessageId,
        ?DateTimeImmutable $scheduledFor,
        ?DateTimeImmutable $sentAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->namespace = $namespace;
        $this->name = $name;
        $this->newsIds = $newsIds;
        $this->templateId = $templateId;
        $this->audienceId = $audienceId;
        $this->status = $status;
        $this->providerCampaignId = $providerCampaignId;
        $this->providerMessageId = $providerMessageId;
        $this->scheduledFor = $scheduledFor;
        $this->sentAt = $sentAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int[]
     */
    public function getNewsIds(): array
    {
        return $this->newsIds;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function getAudienceId(): ?string
    {
        return $this->audienceId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProviderCampaignId(): ?string
    {
        return $this->providerCampaignId;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function getScheduledFor(): ?DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
