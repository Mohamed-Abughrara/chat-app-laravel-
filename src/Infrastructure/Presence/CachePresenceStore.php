<?php

declare(strict_types=1);

namespace Src\Infrastructure\Presence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Src\Application\Ports\PresenceStore;

/** Presence in the Laravel cache (Redis in production). Key TTL = timeout; an index lets us sweep expired users. */
final class CachePresenceStore implements PresenceStore
{
    private const INDEX = 'presence:index';

    public function __construct(private readonly int $ttlSeconds) {}

    public function touch(int $userId, string $deviceId): bool
    {
        $wasOffline = ! Cache::has($this->key($userId));
        $now = time();
        Cache::put($this->key($userId), $now, $this->ttlSeconds);
        Cache::forever($this->lastKey($userId), $now);

        if ($wasOffline) {
            $this->mutateIndex(fn (array $ids) => array_values(array_unique([...$ids, $userId])));
        }

        return $wasOffline;
    }

    public function markOffline(int $userId): bool
    {
        $wasOnline = Cache::has($this->key($userId));
        Cache::forget($this->key($userId));
        $this->mutateIndex(fn (array $ids) => array_values(array_diff($ids, [$userId])));

        return $wasOnline;
    }

    public function isOnline(int $userId): bool
    {
        return Cache::has($this->key($userId));
    }

    public function lastActive(int $userId): ?DateTimeImmutable
    {
        $ts = Cache::get($this->lastKey($userId));

        return $ts === null ? null : (new DateTimeImmutable('@'.$ts))->setTimezone(new DateTimeZone('UTC'));
    }

    public function sweepExpired(): array
    {
        $expired = [];
        $this->mutateIndex(function (array $ids) use (&$expired) {
            foreach ($ids as $id) {
                if (! Cache::has($this->key($id))) {
                    $expired[] = $id;
                }
            }

            return array_values(array_diff($ids, $expired));
        });

        return $expired;
    }

    private function key(int $userId): string
    {
        return "presence:user:$userId";
    }

    private function lastKey(int $userId): string
    {
        return "presence:last:$userId";
    }

    /** @param callable(int[]):int[] $fn */
    private function mutateIndex(callable $fn): void
    {
        Cache::lock('presence:index:lock', 5)->block(5, function () use ($fn) {
            $ids = array_map('intval', Cache::get(self::INDEX, []));
            Cache::forever(self::INDEX, $fn($ids));
        });
    }
}
