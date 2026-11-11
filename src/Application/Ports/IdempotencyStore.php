<?php

declare(strict_types=1);

namespace Src\Application\Ports;

interface IdempotencyStore
{
    /**
     * Atomically stores $value under $key. Returns the previously stored value when the key
     * already existed (and stores nothing), or null when this call claimed the key.
     *
     * @param  array<string,mixed>  $value
     * @return array<string,mixed>|null
     */
    public function putIfAbsent(string $key, array $value, int $ttlSeconds): ?array;
}
