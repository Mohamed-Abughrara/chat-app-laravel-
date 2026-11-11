<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Domain\Chat\Repositories\ConversationRepository;
use Src\Domain\Chat\Repositories\GroupRepository;

final class ListConversations
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly GroupRepository $groups,
    ) {}

    /** @return array{direct:array<int,array{id:int,peer_id:int}>,groups:array<int,array<string,mixed>>} */
    public function execute(int $userId): array
    {
        return [
            'direct' => $this->conversations->forUser($userId),
            'groups' => array_map(fn ($g) => GroupView::from($g), $this->groups->forUser($userId)),
        ];
    }
}
