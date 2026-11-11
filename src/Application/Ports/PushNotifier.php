<?php

declare(strict_types=1);

namespace Src\Application\Ports;

interface PushNotifier
{
    /** @param string[] $pushTokens */
    public function notify(int $userId, array $pushTokens, string $title, string $body): void;
}
