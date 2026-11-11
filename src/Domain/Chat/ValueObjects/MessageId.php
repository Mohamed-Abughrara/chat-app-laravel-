<?php

declare(strict_types=1);

namespace Src\Domain\Chat\ValueObjects;

use InvalidArgumentException;

/** 64-bit Snowflake id. Defines the one stable order of messages. Serialised as a string for JS clients. */
final class MessageId
{
    public function __construct(public readonly int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('MessageId must be positive.');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
