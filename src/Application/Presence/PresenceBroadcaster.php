<?php

declare(strict_types=1);

namespace Src\Application\Presence;

use Src\Application\Ports\PresenceStore;
use Src\Application\Ports\RealtimeGateway;
use Src\Domain\Identity\UserRepository;

/** Tells a user's friends that presence changed (friend-pair fan-out from the PDR). */
final class PresenceBroadcaster
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PresenceStore $presence,
        private readonly RealtimeGateway $realtime,
    ) {}

    public function changed(int $userId, bool $online): void
    {
        $payload = [
            'user_id' => $userId,
            'status' => $online ? 'online' : 'offline',
            'last_active_at' => $this->presence->lastActive($userId)?->format('Y-m-d\TH:i:s\Z'),
        ];
        foreach ($this->users->friendIds($userId) as $friendId) {
            $this->realtime->toUser($friendId, 'presence.changed', $payload);
        }
    }
}
