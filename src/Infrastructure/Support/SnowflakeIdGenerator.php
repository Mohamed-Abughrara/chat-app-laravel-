<?php

declare(strict_types=1);

namespace Src\Infrastructure\Support;

use RuntimeException;
use Src\Application\Ports\IdGenerator;

/**
 * 64-bit Snowflake: 41 bits ms since epoch | 10 bits worker | 12 bits sequence.
 * Refuses to issue ids if the clock moves backwards (PDR R-07).
 *
 * Note: state is per PHP process. Under php-fpm / artisan serve each request may be a fresh process,
 * so the sequence starts at a random offset to make same-millisecond collisions across processes
 * very unlikely; the primary key on message_id still rejects any collision. Run one long-lived
 * process per worker id (Octane/Reverb) in production.
 */
final class SnowflakeIdGenerator implements IdGenerator
{
    public const EPOCH_MS = 1767225600000; // 2026-01-01T00:00:00Z

    private int $lastMs = -1;

    private int $sequence;

    /** @var callable():int */
    private $clock;

    public function __construct(private readonly int $workerId, ?callable $clock = null, ?int $startSequence = null)
    {
        if ($workerId < 0 || $workerId > 1023) {
            throw new RuntimeException('Snowflake worker id must be 0-1023.');
        }
        $this->clock = $clock ?? static fn (): int => (int) floor(microtime(true) * 1000);
        $this->sequence = $startSequence ?? random_int(0, 4095);
    }

    public function next(): int
    {
        $now = ($this->clock)();

        if ($now < $this->lastMs) {
            throw new RuntimeException('Clock moved backwards; refusing to generate ids for '.($this->lastMs - $now).' ms.');
        }

        if ($now === $this->lastMs) {
            $this->sequence = ($this->sequence + 1) & 0xFFF;
            if ($this->sequence === 0) {
                // Sequence exhausted for this millisecond: wait for the next one.
                do {
                    $now = ($this->clock)();
                } while ($now <= $this->lastMs);
            }
        } else {
            $this->sequence = $this->sequence & 0xFFF;
        }

        $this->lastMs = $now;

        return (($now - self::EPOCH_MS) << 22) | ($this->workerId << 12) | $this->sequence;
    }
}
