<?php

declare(strict_types=1);

namespace Src\Application\Presence;

use Src\Application\Ports\PresenceStore;

final class RecordHeartbeat
{
    public function __construct(
        private readonly PresenceStore $presence,
        private readonly PresenceBroadcaster $broadcaster,
    ) {}

    public function execute(int $userId, string $deviceId): void
    {
        if ($this->presence->touch($userId, $deviceId)) {
            $this->broadcaster->changed($userId, true);
        }
    }
}
