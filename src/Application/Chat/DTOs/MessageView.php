<?php

declare(strict_types=1);

namespace Src\Application\Chat\DTOs;

use Src\Domain\Chat\Entities\Message;

/** Wire shape of a message. Ids are strings so JavaScript clients keep 64-bit precision. */
final class MessageView
{
    /** @return array<string,mixed> */
    public static function from(Message $m): array
    {
        return [
            'message_id' => (string) $m->id,
            'conversation_id' => $m->conversationId,
            'is_group' => $m->isGroup(),
            'group_id' => $m->groupId,
            'from' => $m->fromUserId,
            'to' => $m->toUserId,
            'content' => $m->body->value,
            'client_msg_id' => $m->clientMsgId,
            'created_at' => $m->createdAt->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}
