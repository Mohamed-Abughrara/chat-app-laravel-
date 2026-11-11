<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Application\Chat\DTOs\MessageView;
use Src\Domain\Chat\Repositories\InboxRepository;
use Src\Domain\Chat\Repositories\MessageRepository;

/** Catch-up after reconnect: everything in the user's inbox newer than the device's cur_max_message_id. */
final class SyncDevice
{
    public const MAX_BATCH = 500;

    public function __construct(
        private readonly InboxRepository $inbox,
        private readonly MessageRepository $messages,
    ) {}

    /** @return array{messages:array<int,array<string,mixed>>,cur_max_message_id:string,has_more:bool} */
    public function execute(int $userId, int $afterId, int $limit = self::MAX_BATCH): array
    {
        $limit = max(1, min($limit, self::MAX_BATCH));
        $ids = $this->inbox->idsAfter($userId, max(0, $afterId), $limit + 1);
        $hasMore = count($ids) > $limit;
        $ids = array_slice($ids, 0, $limit);

        $found = $this->messages->findMany($ids);
        $views = array_map(fn ($m) => MessageView::from($m), $found);
        $max = $ids === [] ? max(0, $afterId) : max($ids);

        return ['messages' => $views, 'cur_max_message_id' => (string) $max, 'has_more' => $hasMore];
    }
}
