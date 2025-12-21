<?php

declare(strict_types=1);

namespace App\Domain\User;

use JsonSerializable;

class User implements JsonSerializable
{
    private ?int $id;

    private string $username;

    private string $firstName;

    private string $lastName;

    /**
     * @var list<array{namespace:string,is_default:bool}>
     */
    private array $namespaces;

    /**
     * @param list<array{namespace:string,is_default:bool}> $namespaces
     */
    public function __construct(
        ?int $id,
        string $username,
        string $firstName,
        string $lastName,
        array $namespaces = []
    ) {
        $this->id = $id;
        $this->username = strtolower($username);
        $this->firstName = ucfirst($firstName);
        $this->lastName = ucfirst($lastName);
        $this->namespaces = array_values($namespaces);
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getFirstName(): string {
        return $this->firstName;
    }

    public function getLastName(): string {
        return $this->lastName;
    }

    /**
     * @return list<array{namespace:string,is_default:bool}>
     */
    public function getNamespaces(): array {
        return $this->namespaces;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'namespaces' => $this->namespaces,
        ];
    }
}
