<?php

declare(strict_types=1);

namespace Src\Infrastructure\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Generic broadcast carrier: one private channel per user, event name chosen by the caller. */
final class ChatEvent implements ShouldBroadcastNow
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
