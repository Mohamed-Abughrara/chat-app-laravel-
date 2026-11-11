<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Entities;

use DateTimeImmutable;
use Src\Domain\Chat\ValueObjects\MessageBody;
use Src\Domain\Chat\ValueObjects\MessageId;

/**
 * A chat message. Either one-to-one (toUserId set, conversationId = pair id)
 * or group (groupId set, conversationId = group id).
 */
final class Message
{
    public function __construct(
        public readonly MessageId $id,
        public readonly int $conversationId,
        public readonly int $fromUserId,
        public readonly ?int $toUserId,
        public readonly ?int $groupId,
        public readonly MessageBody $body,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?string $clientMsgId = null,
    ) {}

    public function isGroup(): bool
    {
        return $this->groupId !== null;
    }
}
