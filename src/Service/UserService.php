<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use App\Domain\Roles;

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
     * @return array{id:int,username:string,password:string,email:?string,role:string,active:bool}|null
     */
    public function getByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,username,password,email,role,active FROM users WHERE username=?');
        $stmt->execute([strtolower($username)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Find a user by email.
     *
     * @return array{id:int,username:string,password:string,email:?string,role:string,active:bool}|null
     */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,username,password,email,role,active FROM users WHERE email=?');
        $stmt->execute([strtolower($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Find a user by id.
     *
     * @return array{id:int,username:string,password:string,email:?string,role:string,active:bool}|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,username,password,email,role,active FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a new user with the given role.
     */
    public function create(
        string $username,
        string $password,
        ?string $email = null,
        string $role = Roles::CATALOG_EDITOR,
        bool $active = true
    ): void {
        if (!in_array($role, Roles::ALL, true)) {
            $role = Roles::CATALOG_EDITOR;
        }
        $stmt = $this->pdo->prepare('INSERT INTO users(username,password,email,role,active) VALUES(?,?,?,?,?)');
        $stmt->execute([
            strtolower($username),
            password_hash($password, PASSWORD_DEFAULT),
            $email,
            $role,
            $active,
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

    /**
     * Retrieve the email for the given user id.
     */
    public function getEmail(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id=?');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : null;
    }

    /**
     * Update the email for the given user id.
     */
    public function setEmail(int $id, ?string $email): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET email=? WHERE id=?');
        $stmt->execute([$email, $id]);
    }

    /**
     * Retrieve all users ordered by their id.
     *
     * @return list<array{id:int,username:string,email:?string,role:string,active:bool}>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id,username,email,role,active FROM users ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace the entire user list with the provided data.
     *
     * @param list<array{id?:int,username:string,email?:?string,role:string,password?:string,active?:bool}> $users
     */
    public function saveAll(array $users): void
    {
        $this->pdo->beginTransaction();
        $existing = [];
        foreach ($this->pdo->query('SELECT id FROM users') as $row) {
            $existing[(int) $row['id']] = true;
        }

        $insert = $this->pdo->prepare('INSERT INTO users(username,password,email,role,active) VALUES(?,?,?,?,?)');
        $update = $this->pdo->prepare('UPDATE users SET username=?,email=?,role=?,active=? WHERE id=?');
        $updatePass = $this->pdo->prepare('UPDATE users SET password=? WHERE id=?');
        $delete = $this->pdo->prepare('DELETE FROM users WHERE id=?');

        foreach ($users as $u) {
            $id = isset($u['id']) ? (int) $u['id'] : 0;
            $username = strtolower((string) $u['username']);
            $role = (string) $u['role'];
            $email = isset($u['email']) ? (string) $u['email'] : null;
            if (!in_array($role, Roles::ALL, true)) {
                $role = Roles::CATALOG_EDITOR;
            }
            $pass = $u['password'] ?? '';
            $active = isset($u['active']) ? (bool)$u['active'] : true;

            if ($id === 0 || !isset($existing[$id])) {
                if ($pass === '') {
                    $pass = bin2hex(random_bytes(8));
                }
                $insert->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $email, $role, $active]);
                continue;
            }

            $update->execute([$username, $email, $role, $active, $id]);
            if ($pass !== '') {
                $updatePass->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            unset($existing[$id]);
        }

        foreach (array_keys($existing) as $id) {
            $delete->execute([$id]);
        }

        $this->pdo->commit();
    }
}
