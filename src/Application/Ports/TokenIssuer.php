<?php

declare(strict_types=1);

namespace Src\Application\Ports;

interface TokenIssuer
{
    /** Issues a bearer token bound to one device. */
    public function issue(int $userId, string $deviceId): string;

    public function revoke(int $userId, string $deviceId): void;
}
