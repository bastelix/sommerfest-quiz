<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\User\User;
use App\Domain\User\UserNotFoundException;
use App\Domain\User\UserRepository;

class InMemoryUserRepository implements UserRepository
{
    /**
     * @var User[]
     */
    private array $users;

    /**
     * @param User[]|null $users
     */
    public function __construct(?array $users = null) {
        $defaultNamespaces = [
            [
                'namespace' => 'default',
                'is_default' => true,
            ],
        ];
        $this->users = $users ?? [
            1 => new User(1, 'bill.gates', 'Bill', 'Gates', $defaultNamespaces),
            2 => new User(2, 'steve.jobs', 'Steve', 'Jobs', $defaultNamespaces),
            3 => new User(3, 'mark.zuckerberg', 'Mark', 'Zuckerberg', $defaultNamespaces),
            4 => new User(4, 'evan.spiegel', 'Evan', 'Spiegel', $defaultNamespaces),
            5 => new User(5, 'jack.dorsey', 'Jack', 'Dorsey', $defaultNamespaces),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array {
        return array_values($this->users);
    }

    /**
     * {@inheritdoc}
     */
    public function findUserOfId(int $id): User {
        if (!isset($this->users[$id])) {
            throw new UserNotFoundException();
        }

        return $this->users[$id];
    }
}
