<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Domain\Chat\Repositories\ReceiptRepository;

final class EloquentReceiptRepository implements ReceiptRepository
{
    public function markDelivered(int $messageId, int $userId): void
    {
        DB::table('message_receipts')->insertOrIgnore([
            'message_id' => $messageId,
            'user_id' => $userId,
            'delivered_at' => now(),
        ]);
    }
}
