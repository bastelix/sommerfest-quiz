<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,email:string,name:?string,stripe_customer_id:?string,status:string,created_at:string,updated_at:string}|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array{id:int,email:string,name:?string,stripe_customer_id:?string,status:string,created_at:string,updated_at:string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new account and return its ID.
     */
    public function create(string $email, ?string $name = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO accounts (email, name) VALUES (?, ?) RETURNING id'
        );
        $stmt->execute([strtolower(trim($email)), $name]);

        return (int) $stmt->fetchColumn();
    }

    public function updateStripeCustomerId(int $id, string $stripeCustomerId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE accounts SET stripe_customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([$stripeCustomerId, $id]);
    }

    public function updateName(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE accounts SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([trim($name), $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE accounts SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
    }
}
