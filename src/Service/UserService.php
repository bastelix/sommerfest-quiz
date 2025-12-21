<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Roles;
use App\Repository\UserNamespaceRepository;
use App\Support\UsernameBlockedException;
use App\Support\UsernameGuard;
use PDO;
use PDOException;
use Throwable;

/**
 * Service for user and role management.
 */
class UserService
{
    private PDO $pdo;

    private UsernameGuard $usernameGuard;

    private UserNamespaceRepository $namespaceRepository;

    public function __construct(
        PDO $pdo,
        ?UsernameGuard $usernameGuard = null,
        ?UserNamespaceRepository $namespaceRepository = null
    ) {
        $this->pdo = $pdo;
        $this->usernameGuard = $usernameGuard ?? UsernameGuard::fromConfigFile(null, $pdo);
        $this->namespaceRepository = $namespaceRepository ?? new UserNamespaceRepository($pdo);
    }

    /**
     * Find a user by username.
     *
     * @return array{
     *     id:int,
     *     username:string,
     *     password:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     namespaces:list<array{namespace:string,is_default:bool}>
     * }|null
     */
    public function getByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id,username,password,email,role,active FROM users WHERE LOWER(username) = LOWER(?)'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->attachNamespaces($row) : null;
    }

    /**
     * Find a user by email.
     *
     * @return array{
     *     id:int,
     *     username:string,
     *     password:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     namespaces:list<array{namespace:string,is_default:bool}>
     * }|null
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id,username,password,email,role,active FROM users WHERE LOWER(email) = LOWER(?)'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->attachNamespaces($row) : null;
    }

    /**
     * Find a user by id.
     *
     * @return array{
     *     id:int,
     *     username:string,
     *     password:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     namespaces:list<array{namespace:string,is_default:bool}>
     * }|null
     */
    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT id,username,password,email,role,active FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $this->attachNamespaces($row) : null;
    }

    /**
     * Create a new user with the given role.
     *
     * @throws UsernameBlockedException
     */
    public function create(
        string $username,
        string $password,
        ?string $email = null,
        string $role = Roles::CATALOG_EDITOR,
        bool $active = true
    ): void {
        $this->usernameGuard->assertAllowed($username);
        if (!in_array($role, Roles::ALL, true)) {
            $role = Roles::CATALOG_EDITOR;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users(username,password,email,role,active) VALUES(?,?,?,?,?) RETURNING id'
            );
            $stmt->execute([
                strtolower($username),
                password_hash($password, PASSWORD_DEFAULT),
                $email,
                $role,
                $active,
            ]);

            $userId = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($userId > 0) {
                $this->namespaceRepository->ensureDefaultNamespace($userId);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Update the password for a user identified by id.
     */
    public function updatePassword(int $id, string $password): void {
        $stmt = $this->pdo->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id,
        ]);
    }

    /**
     * Retrieve the email for the given user id.
     */
    public function getEmail(int $id): ?string {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id=?');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : null;
    }

    /**
     * Update the email for the given user id.
     */
    public function setEmail(int $id, ?string $email): void {
        $stmt = $this->pdo->prepare('UPDATE users SET email=? WHERE id=?');
        $stmt->execute([$email, $id]);
    }

    /**
     * Retrieve all users ordered by their position.
     *
     * @return list<array{
     *     id:int,
     *     username:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     position:int
     * }>
     */
    public function getAll(): array {
        $stmt = $this->pdo->query('SELECT id,username,email,role,active,position FROM users ORDER BY position');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace the entire user list with the provided data.
     *
     * @param list<array{
     *     id?:int,
     *     username?:string,
     *     email?:?string,
     *     role?:string,
     *     password?:string,
     *     active?:bool,
     *     position?:int
     * }> $users
     *
     * @throws UsernameBlockedException
     * @throws PDOException
     */
    public function saveAll(array $users): void {
        foreach ($users as $candidate) {
            if (!isset($candidate['username'])) {
                continue;
            }
            $this->usernameGuard->assertAllowed((string) $candidate['username']);
        }

        $this->pdo->beginTransaction();
        $existing = [];
        foreach ($this->pdo->query('SELECT id FROM users') as $row) {
            $existing[(int) $row['id']] = true;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO users(username,password,email,role,active,position) VALUES(?,?,?,?,?,?) RETURNING id'
        );
        $update = $this->pdo->prepare('UPDATE users SET username=?,email=?,role=?,active=?,position=? WHERE id=?');
        $updatePass = $this->pdo->prepare('UPDATE users SET password=? WHERE id=?');
        $delete = $this->pdo->prepare('DELETE FROM users WHERE id=?');

        foreach ($users as $pos => $u) {
            if (!isset($u['username'])) {
                continue;
            }
            $id = isset($u['id']) ? (int) $u['id'] : 0;
            $rawUsername = (string) $u['username'];
            $username = strtolower($rawUsername);
            $role = isset($u['role']) ? (string) $u['role'] : Roles::CATALOG_EDITOR;
            $email = isset($u['email']) ? (string) $u['email'] : null;
            if (!in_array($role, Roles::ALL, true)) {
                $role = Roles::CATALOG_EDITOR;
            }
            $pass = $u['password'] ?? '';
            $active = isset($u['active']) ? (bool) $u['active'] : true;
            $position = $pos;

            if ($id === 0 || !isset($existing[$id])) {
                if ($pass === '') {
                    $pass = bin2hex(random_bytes(8));
                }
                $insert->execute([
                    $username,
                    password_hash($pass, PASSWORD_DEFAULT),
                    $email,
                    $role,
                    $active,
                    $position,
                ]);
                $insertedId = (int) $insert->fetchColumn();
                $insert->closeCursor();
                if ($insertedId > 0) {
                    $this->namespaceRepository->ensureDefaultNamespace($insertedId);
                }
                continue;
            }

            $update->execute([$username, $email, $role, $active, $position, $id]);
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

    /**
     * @param array{id:int} $row
     * @return array{
     *     id:int,
     *     username:string,
     *     password:string,
     *     email:?string,
     *     role:string,
     *     active:bool,
     *     namespaces:list<array{namespace:string,is_default:bool}>
     * }
     */
    private function attachNamespaces(array $row): array {
        $row['namespaces'] = $this->namespaceRepository->loadForUser((int) $row['id']);

        return $row;
    }
}
