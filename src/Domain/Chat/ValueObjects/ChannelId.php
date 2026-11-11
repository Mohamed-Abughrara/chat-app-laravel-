<?php

declare(strict_types=1);

namespace Src\Domain\Chat\ValueObjects;

use InvalidArgumentException;

/** Identifier of a group channel. */
final class ChannelId
{
    public function __construct(public readonly int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('ChannelId must be positive.');
        }
    }
}
