<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Events;

use Src\Domain\Chat\Entities\Message;

/** Raised once a message is accepted. recipientIds includes the sender (for their other devices). */
final class MessageSent
{
    /** @param int[] $recipientIds */
    public function __construct(
        public readonly Message $message,
        public readonly array $recipientIds,
    ) {}
}
