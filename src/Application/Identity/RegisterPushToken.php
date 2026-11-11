<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Domain\Identity\DeviceRepository;

final class RegisterPushToken
{
    public function __construct(private readonly DeviceRepository $devices) {}

    public function execute(int $userId, string $deviceId, string $token): void
    {
        $this->devices->setPushToken($deviceId, $userId, $token);
    }
}
