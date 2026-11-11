<?php

declare(strict_types=1);

namespace Src\Application\Presence;

use Src\Application\Ports\PresenceStore;

/** Marks users offline once their heartbeat is older than the timeout (R-05: do not rely on key-expiry events). */
final class SweepPresence
{
    public function __construct(
        private readonly PresenceStore $presence,
        private readonly PresenceBroadcaster $broadcaster,
    ) {}

    /** @return int[] users newly marked offline */
    public function execute(): array
    {
        $expired = $this->presence->sweepExpired();
        foreach ($expired as $userId) {
            $this->broadcaster->changed($userId, false);
        }

        return $expired;
    }
}
