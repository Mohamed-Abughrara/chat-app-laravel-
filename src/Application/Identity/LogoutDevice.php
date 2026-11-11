<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Application\Ports\TokenIssuer;
use Src\Application\Presence\GoOffline;
use Src\Domain\Identity\DeviceRepository;

final class LogoutDevice
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly TokenIssuer $tokens,
        private readonly GoOffline $goOffline,
    ) {}

    public function execute(int $userId, string $deviceId): void
    {
        $this->tokens->revoke($userId, $deviceId);
        $this->devices->remove($deviceId);
        $this->goOffline->execute($userId);
    }
}
