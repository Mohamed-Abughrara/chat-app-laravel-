<?php

declare(strict_types=1);

namespace Src\Domain\Identity;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {}
}
