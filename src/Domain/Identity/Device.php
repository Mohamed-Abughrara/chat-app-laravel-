<?php

declare(strict_types=1);

namespace Src\Domain\Identity;

final class Device
{
    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly string $platform,
        public readonly ?string $pushToken = null,
    ) {}
}
