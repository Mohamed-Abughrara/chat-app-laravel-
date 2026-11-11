<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Repositories;

interface ConversationRepository
{
    /** Id of the 1-on-1 conversation for a user pair, created on first use. */
    public function findOrCreateForPair(int $userA, int $userB): int;

    /** @return int[]|null the two participants, or null when unknown */
    public function participants(int $conversationId): ?array;

    /** @return array<int,array{id:int,peer_id:int}> */
    public function forUser(int $userId): array;
}
