<?php

declare(strict_types=1);

namespace Src\Application\Presence;

use Src\Application\Ports\PresenceStore;

final class GoOffline
{
    public function __construct(
        private readonly PresenceStore $presence,
        private readonly PresenceBroadcaster $broadcaster,
    ) {}

    public function execute(int $userId): void
    {
        if ($this->presence->markOffline($userId)) {
            $this->broadcaster->changed($userId, false);
        }
    }
}
