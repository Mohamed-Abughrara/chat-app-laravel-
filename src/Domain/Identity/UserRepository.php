<?php

declare(strict_types=1);

namespace Src\Domain\Identity;

interface UserRepository
{
    public function create(string $name, string $email, string $passwordHash): User;

    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function update(int $id, string $name): void;

    /** @return User[] */
    public function friends(int $userId): array;

    /** @return int[] */
    public function friendIds(int $userId): array;

    /** Stores the friendship in both directions. */
    public function addFriend(int $userId, int $friendId): void;
}
