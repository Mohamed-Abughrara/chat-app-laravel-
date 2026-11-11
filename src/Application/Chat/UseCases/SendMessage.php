<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Src\Application\Ports\IdempotencyStore;
use Src\Application\Ports\IdGenerator;
use Src\Application\Ports\MessageQueue;
use Src\Domain\Chat\Entities\Message;
use Src\Domain\Chat\Events\MessageSent;
use Src\Domain\Chat\Repositories\ConversationRepository;
use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Chat\ValueObjects\MessageBody;
use Src\Domain\Chat\ValueObjects\MessageId;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\InvalidMessage;
use Src\Domain\Shared\NotAuthorized;
use Src\Domain\Shared\NotFound;

/**
 * Accepts a message: validates, dedupes on client_msg_id (FR-10), assigns a Snowflake id,
 * publishes to the sync queue and returns the acknowledgement (FR-09).
 */
final class SendMessage
{
    private const IDEMPOTENCY_TTL = 86_400; // 24 h

    public function __construct(
        private readonly UserRepository $users,
        private readonly GroupRepository $groups,
        private readonly ConversationRepository $conversations,
        private readonly IdGenerator $ids,
        private readonly MessageQueue $queue,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array{client_msg_id:string,message_id:string,created_at:string,duplicate:bool}
     */
    public function execute(int $fromUserId, string $clientMsgId, string $content, ?int $toUserId, ?int $groupId): array
    {
        if (($toUserId === null) === ($groupId === null)) {
            throw new InvalidMessage('Provide exactly one of to_user_id or group_id.');
        }
        if (trim($clientMsgId) === '') {
            throw new InvalidMessage('client_msg_id is required.');
        }

        $body = MessageBody::fromString($content);

        if ($groupId !== null) {
            $group = $this->groups->find($groupId) ?? throw new NotFound('Group not found.');
            if (! $group->isMember($fromUserId)) {
                throw new NotAuthorized('You are not a member of this group.');
            }
            $conversationId = $groupId;
            $recipients = $group->memberIds();
        } else {
            if ($toUserId === $fromUserId) {
                throw new InvalidMessage('You cannot message yourself.');
            }
            if ($this->users->find($toUserId) === null) {
                throw new NotFound('Recipient not found.');
            }
            $conversationId = $this->conversations->findOrCreateForPair($fromUserId, $toUserId);
            $recipients = [$toUserId, $fromUserId];
        }

        $id = $this->ids->next();
        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $ack = [
            'client_msg_id' => $clientMsgId,
            'message_id' => (string) $id,
            'created_at' => $createdAt->format('Y-m-d\TH:i:s.v\Z'),
            'duplicate' => false,
        ];

        $existing = $this->idempotency->putIfAbsent("send:$fromUserId:$clientMsgId", $ack, self::IDEMPOTENCY_TTL);
        if ($existing !== null) {
            return [...$existing, 'duplicate' => true];
        }

        $message = new Message(new MessageId($id), $conversationId, $fromUserId, $toUserId, $groupId, $body, $createdAt, $clientMsgId);
        $this->queue->publish(new MessageSent($message, $recipients));

        return $ack;
    }
}
