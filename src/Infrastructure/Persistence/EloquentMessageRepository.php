<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Src\Domain\Chat\Entities\Message;
use Src\Domain\Chat\Repositories\MessageRepository;
use Src\Domain\Chat\ValueObjects\MessageBody;
use Src\Domain\Chat\ValueObjects\MessageId;

final class EloquentMessageRepository implements MessageRepository
{
    public function save(Message $m): void
    {
        $createdAt = $m->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');

        if ($m->isGroup()) {
            DB::table('group_messages')->insertOrIgnore([
                'message_id' => $m->id->value,
                'channel_id' => $m->groupId,
                'user_id' => $m->fromUserId,
                'content' => $m->body->value,
                'client_msg_id' => $m->clientMsgId,
                'created_at' => $createdAt,
            ]);

            return;
        }

        DB::table('messages')->insertOrIgnore([
            'message_id' => $m->id->value,
            'conversation_id' => $m->conversationId,
            'message_from' => $m->fromUserId,
            'message_to' => $m->toUserId,
            'content' => $m->body->value,
            'client_msg_id' => $m->clientMsgId,
            'created_at' => $createdAt,
        ]);
    }

    public function find(int $messageId): ?Message
    {
        return $this->findMany([$messageId])[0] ?? null;
    }

    public function history(bool $isGroup, int $conversationId, ?int $beforeId, int $limit): array
    {
        $q = $isGroup
            ? DB::table('group_messages')->where('channel_id', $conversationId)
            : DB::table('messages')->where('conversation_id', $conversationId);

        if ($beforeId !== null) {
            $q->where('message_id', '<', $beforeId);
        }

        return $q->orderByDesc('message_id')->limit($limit)->get()
            ->map(fn ($r) => $this->hydrate($r, $isGroup))->all();
    }

    public function findMany(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $direct = DB::table('messages')->whereIn('message_id', $messageIds)->get()
            ->map(fn ($r) => $this->hydrate($r, false));
        $group = DB::table('group_messages')->whereIn('message_id', $messageIds)->get()
            ->map(fn ($r) => $this->hydrate($r, true));

        return $direct->concat($group)->sortBy(fn (Message $m) => $m->id->value)->values()->all();
    }

    private function hydrate(object $r, bool $isGroup): Message
    {
        $createdAt = new DateTimeImmutable((string) $r->created_at, new DateTimeZone('UTC'));

        return new Message(
            new MessageId((int) $r->message_id),
            $isGroup ? (int) $r->channel_id : (int) $r->conversation_id,
            $isGroup ? (int) $r->user_id : (int) $r->message_from,
            $isGroup ? null : (int) $r->message_to,
            $isGroup ? (int) $r->channel_id : null,
            MessageBody::fromString($r->content),
            $createdAt,
            $r->client_msg_id,
        );
    }
}
