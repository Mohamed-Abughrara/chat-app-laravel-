<?php

declare(strict_types=1);

namespace Src\Application\Ports;

/** Pushes events to a user's connected devices (Reverb private channel private-user.{id}). */
interface RealtimeGateway
{
    /** @param array<string,mixed> $payload */
    public function toUser(int $userId, string $event, array $payload): void;
}
