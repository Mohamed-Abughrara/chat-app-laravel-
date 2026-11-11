<?php

declare(strict_types=1);

namespace Src\Infrastructure\Push;

use Illuminate\Support\Facades\Log;
use Src\Application\Ports\PushNotifier;

/** Stand-in for APNs/FCM. Never logs message content (PDR section 12); only counts. */
final class LogPushNotifier implements PushNotifier
{
    public function notify(int $userId, array $pushTokens, string $title, string $body): void
    {
        Log::info('push.dispatch', ['user_id' => $userId, 'devices' => count($pushTokens)]);
    }
}
