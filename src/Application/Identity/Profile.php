<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\InvalidMessage;
use Src\Domain\Shared\NotFound;

/** GetProfile and UpdateProfile. */
final class Profile
{
    public function __construct(private readonly UserRepository $users) {}

    /** @return array{id:int,name:string,email:string} */
    public function get(int $userId): array
    {
        $u = $this->users->find($userId) ?? throw new NotFound('User not found.');

        return ['id' => (int) $u->id, 'name' => $u->name, 'email' => $u->email];
    }

    /** @return array{id:int,name:string,email:string} */
    public function update(int $userId, string $name): array
    {
        if (trim($name) === '') {
            throw new InvalidMessage('Name is required.');
        }
        $this->users->update($userId, trim($name));

        return $this->get($userId);
    }
}
