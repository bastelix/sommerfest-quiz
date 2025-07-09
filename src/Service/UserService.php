<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service for user and role management.
 */
class UserService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a user by username.
     *
     * @return array{id:int,username:string,password:string,role:string}|null
     */
    public function getByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,username,password,role FROM users WHERE username=?');
        $stmt->execute([strtolower($username)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a new user with the given role.
     */
    public function create(string $username, string $password, string $role = 'user'): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
        $stmt->execute([
            strtolower($username),
            password_hash($password, PASSWORD_DEFAULT),
            $role,
        ]);
    }

    /**
     * Update the password for a user identified by id.
     */
    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id,
        ]);
    }
}
