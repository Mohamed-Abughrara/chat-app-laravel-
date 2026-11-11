<?php

declare(strict_types=1);

namespace Src\Infrastructure\Support;

use Illuminate\Support\Facades\Hash;
use Src\Application\Ports\PasswordHasher;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return Hash::check($plain, $hash);
    }
}
