<?php

declare(strict_types=1);

namespace Src\Infrastructure\Realtime;

use Illuminate\Support\Facades\Log;
use Src\Application\Ports\RealtimeGateway;
use Throwable;

final class ReverbRealtimeGateway implements RealtimeGateway
{
    public function toUser(int $userId, string $event, array $payload): void
    {
        try {
            broadcast(new ChatEvent($userId, $event, $payload));
        } catch (Throwable $e) {
            // A down WebSocket tier must not fail the send path: the message is already durable
            // and the device will pick it up through /sync on reconnect.
            Log::warning('realtime.broadcast_failed', ['user_id' => $userId, 'event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
