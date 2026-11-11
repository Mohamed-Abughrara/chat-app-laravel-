<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Application\Chat\DTOs\MessageView;
use Src\Application\Ports\PresenceStore;
use Src\Application\Ports\PushNotifier;
use Src\Application\Ports\RealtimeGateway;
use Src\Domain\Chat\Events\MessageSent;
use Src\Domain\Chat\Repositories\InboxRepository;
use Src\Domain\Chat\Repositories\MessageRepository;
use Src\Domain\Identity\DeviceRepository;
use Src\Domain\Identity\UserRepository;

/**
 * Queue consumer: stores the message once, copies a small pointer into every recipient inbox,
 * pushes message.new to online recipients and a push notification to offline ones.
 * Idempotent: replaying the same event does not duplicate rows.
 */
final class DeliverMessage
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly InboxRepository $inbox,
        private readonly PresenceStore $presence,
        private readonly RealtimeGateway $realtime,
        private readonly PushNotifier $push,
        private readonly DeviceRepository $devices,
        private readonly UserRepository $users,
    ) {}

    public function execute(MessageSent $event): void
    {
        $m = $event->message;
        $this->messages->save($m);

        foreach ($event->recipientIds as $userId) {
            $this->inbox->add($userId, $m->id->value, $m->conversationId, $m->isGroup());
        }

        $payload = MessageView::from($m);
        $senderName = $this->users->find($m->fromUserId)?->name ?? 'Someone';

        foreach ($event->recipientIds as $userId) {
            // The sender's other devices always get the event; the sending device dedupes by client_msg_id.
            if ($userId === $m->fromUserId || $this->presence->isOnline($userId)) {
                $this->realtime->toUser($userId, 'message.new', $payload);

                continue;
            }
            $this->push->notify($userId, $this->devices->pushTokens($userId), $senderName, mb_substr($m->body->value, 0, 120));
        }
    }
}
