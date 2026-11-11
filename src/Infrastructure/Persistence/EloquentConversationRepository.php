<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Domain\Chat\Repositories\ConversationRepository;

final class EloquentConversationRepository implements ConversationRepository
{
    public function findOrCreateForPair(int $userA, int $userB): int
    {
        $low = min($userA, $userB);
        $high = max($userA, $userB);

        DB::table('conversations')->insertOrIgnore(['user_low' => $low, 'user_high' => $high]);

        return (int) DB::table('conversations')->where('user_low', $low)->where('user_high', $high)->value('id');
    }

    public function participants(int $conversationId): ?array
    {
        $row = DB::table('conversations')->where('id', $conversationId)->first();

        return $row === null ? null : [(int) $row->user_low, (int) $row->user_high];
    }

    public function forUser(int $userId): array
    {
        return DB::table('conversations')
            ->where('user_low', $userId)->orWhere('user_high', $userId)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'peer_id' => (int) ((int) $r->user_low === $userId ? $r->user_high : $r->user_low),
            ])->all();
    }
}
