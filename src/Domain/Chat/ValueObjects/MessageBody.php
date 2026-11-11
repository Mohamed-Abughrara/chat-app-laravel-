<?php

declare(strict_types=1);

namespace Src\Domain\Chat\ValueObjects;

use Src\Domain\Shared\InvalidMessage;

final class MessageBody
{
    public const MAX_LENGTH = 100_000;

    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidMessage('Message is not valid UTF-8.');
        }
        if (trim($value) === '') {
            throw new InvalidMessage('Message is empty.');
        }
        if (mb_strlen($value, 'UTF-8') > self::MAX_LENGTH) {
            throw new InvalidMessage('Message exceeds '.self::MAX_LENGTH.' characters.');
        }

        return new self($value);
    }
}
