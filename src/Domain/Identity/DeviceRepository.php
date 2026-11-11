<?php

declare(strict_types=1);

namespace Src\Domain\Identity;

interface DeviceRepository
{
    public function register(int $userId, string $platform): Device;

    public function find(string $deviceId): ?Device;

    public function remove(string $deviceId): void;

    public function setPushToken(string $deviceId, int $userId, string $token): void;

    /** @return string[] push tokens of every device of the user */
    public function pushTokens(int $userId): array;
}
