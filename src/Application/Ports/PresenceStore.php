<?php

declare(strict_types=1);

namespace Src\Application\Ports;

use DateTimeImmutable;

interface PresenceStore
{
    /** Records a heartbeat. Returns true when the user was offline before this call. */
    public function touch(int $userId, string $deviceId): bool;

    /** Returns true when the user was online before this call. */
    public function markOffline(int $userId): bool;

    public function isOnline(int $userId): bool;

    public function lastActive(int $userId): ?DateTimeImmutable;

    /**
     * Removes users whose heartbeat expired and returns their ids.
     *
     * @return int[]
     */
    public function sweepExpired(): array;
}
