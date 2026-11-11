<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Application\Ports\PasswordHasher;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\Conflict;
use Src\Domain\Shared\InvalidMessage;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {}

    /** @return array{id:int,name:string,email:string} */
    public function execute(string $name, string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        if (mb_strlen($password) < 8) {
            throw new InvalidMessage('Password must be at least 8 characters.');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new Conflict('Email is already registered.');
        }

        $user = $this->users->create(trim($name), $email, $this->hasher->hash($password));

        return ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
