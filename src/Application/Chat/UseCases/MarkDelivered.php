<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Application\Ports\RealtimeGateway;
use Src\Domain\Chat\Repositories\ConversationRepository;
use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Chat\Repositories\MessageRepository;
use Src\Domain\Chat\Repositories\ReceiptRepository;
use Src\Domain\Shared\NotAuthorized;
use Src\Domain\Shared\NotFound;

/** Recipient device reports delivery; the sender is told (FR-09). */
final class MarkDelivered
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly ReceiptRepository $receipts,
        private readonly ConversationRepository $conversations,
        private readonly GroupRepository $groups,
        private readonly RealtimeGateway $realtime,
    ) {}

    public function execute(int $userId, int $messageId): void
    {
        $m = $this->messages->find($messageId) ?? throw new NotFound('Message not found.');

        $allowed = $m->isGroup()
            ? ($this->groups->find((int) $m->groupId)?->isMember($userId) ?? false)
            : in_array($userId, $this->conversations->participants($m->conversationId) ?? [], true);
        if (! $allowed || $m->fromUserId === $userId) {
            throw new NotAuthorized('Cannot acknowledge this message.');
        }

        $this->receipts->markDelivered($messageId, $userId);
        $this->realtime->toUser($m->fromUserId, 'message.status', [
            'message_id' => (string) $m->id,
            'status' => 'delivered',
            'user_id' => $userId,
        ]);
    }
}
