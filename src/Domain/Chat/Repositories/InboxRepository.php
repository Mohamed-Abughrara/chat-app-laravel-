<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Repositories;

interface InboxRepository
{
    public function add(int $userId, int $messageId, int $conversationId, bool $isGroup): void;

    /**
     * Message ids in this user's inbox greater than $afterId, ascending.
     *
     * @return int[]
     */
    public function idsAfter(int $userId, int $afterId, int $limit): array;
}
