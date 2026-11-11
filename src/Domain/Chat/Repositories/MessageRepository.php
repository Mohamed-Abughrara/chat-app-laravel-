<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Repositories;

use Src\Domain\Chat\Entities\Message;

interface MessageRepository
{
    public function save(Message $message): void;

    public function find(int $messageId): ?Message;

    /**
     * Newest first, strictly older than $beforeId when given.
     *
     * @return Message[]
     */
    public function history(bool $isGroup, int $conversationId, ?int $beforeId, int $limit): array;

    /**
     * @param  int[]  $messageIds
     * @return Message[] ordered by id ascending
     */
    public function findMany(array $messageIds): array;
}
