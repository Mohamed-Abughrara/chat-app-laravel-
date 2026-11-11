<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Application\Ports\PresenceStore;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\InvalidMessage;
use Src\Domain\Shared\NotFound;

/** ListFriends (with last known presence) and AddFriend. */
final class Friends
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PresenceStore $presence,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function list(int $userId): array
    {
        return array_map(fn ($f) => [
            'id' => (int) $f->id,
            'name' => $f->name,
            'email' => $f->email,
            'status' => $this->presence->isOnline((int) $f->id) ? 'online' : 'offline',
            'last_active_at' => $this->presence->lastActive((int) $f->id)?->format('Y-m-d\TH:i:s\Z'),
        ], $this->users->friends($userId));
    }

    /** @return array<int,array<string,mixed>> the updated friend list */
    public function addByEmail(int $userId, string $email): array
    {
        $friend = $this->users->findByEmail(mb_strtolower(trim($email))) ?? throw new NotFound('No user with that email.');
        if ($friend->id === $userId) {
            throw new InvalidMessage('You cannot add yourself.');
        }
        $this->users->addFriend($userId, (int) $friend->id);

        return $this->list($userId);
    }
}
