<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\Cache;
use Src\Application\Ports\IdempotencyStore;

/** Atomic claim via Cache::add (Redis SETNX in production). */
final class CacheIdempotencyStore implements IdempotencyStore
{
    public function putIfAbsent(string $key, array $value, int $ttlSeconds): ?array
    {
        $key = 'idem:'.$key;

        if (Cache::add($key, $value, $ttlSeconds)) {
            return null;
        }

        return Cache::get($key);
    }
}
