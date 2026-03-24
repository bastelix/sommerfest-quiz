<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use JsonSerializable;

final class CustomerProfile implements JsonSerializable
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly ?string $displayName,
        private readonly ?string $company,
        private readonly ?string $phone,
        private readonly ?string $avatarUrl,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'displayName' => $this->displayName,
            'company' => $this->company,
            'phone' => $this->phone,
            'avatarUrl' => $this->avatarUrl,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
