<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Domain\Chat\Repositories\InboxRepository;

final class EloquentInboxRepository implements InboxRepository
{
    public function add(int $userId, int $messageId, int $conversationId, bool $isGroup): void
    {
        DB::table('user_inbox')->insertOrIgnore([
            'user_id' => $userId,
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'is_group' => $isGroup,
        ]);
    }

    public function idsAfter(int $userId, int $afterId, int $limit): array
    {
        return DB::table('user_inbox')
            ->where('user_id', $userId)
            ->where('message_id', '>', $afterId)
            ->orderBy('message_id')
            ->limit($limit)
            ->pluck('message_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
