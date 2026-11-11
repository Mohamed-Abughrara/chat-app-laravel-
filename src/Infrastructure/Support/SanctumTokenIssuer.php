<?php

declare(strict_types=1);

namespace Src\Infrastructure\Support;

use Src\Application\Ports\TokenIssuer;

/**
 * One Sanctum token per device, named "device:{id}".
 * The user model is resolved from auth config so Infrastructure never imports the app layer.
 */
final class SanctumTokenIssuer implements TokenIssuer
{
    public function issue(int $userId, string $deviceId): string
    {
        return $this->model()::query()->findOrFail($userId)->createToken('device:'.$deviceId)->plainTextToken;
    }

    public function revoke(int $userId, string $deviceId): void
    {
        $this->model()::query()->find($userId)?->tokens()->where('name', 'device:'.$deviceId)->delete();
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function model(): string
    {
        return config('auth.providers.users.model');
    }
}
