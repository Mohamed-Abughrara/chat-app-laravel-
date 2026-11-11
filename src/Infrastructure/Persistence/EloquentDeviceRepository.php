<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Domain\Identity\Device;
use Src\Domain\Identity\DeviceRepository;

final class EloquentDeviceRepository implements DeviceRepository
{
    public function register(int $userId, string $platform): Device
    {
        $id = 'd-'.Str::lower(Str::random(12));
        DB::table('devices')->insert([
            'id' => $id,
            'user_id' => $userId,
            'platform' => $platform,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new Device($id, $userId, $platform);
    }

    public function find(string $deviceId): ?Device
    {
        $r = DB::table('devices')->where('id', $deviceId)->first();

        return $r === null ? null : new Device($r->id, (int) $r->user_id, $r->platform, $r->push_token);
    }

    public function remove(string $deviceId): void
    {
        DB::table('devices')->where('id', $deviceId)->delete();
    }

    public function setPushToken(string $deviceId, int $userId, string $token): void
    {
        DB::table('devices')->where('id', $deviceId)->where('user_id', $userId)
            ->update(['push_token' => $token, 'updated_at' => now()]);
    }

    public function pushTokens(int $userId): array
    {
        return DB::table('devices')->where('user_id', $userId)->whereNotNull('push_token')->pluck('push_token')->all();
    }
}
