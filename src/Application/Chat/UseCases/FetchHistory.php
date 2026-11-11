<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Application\Chat\DTOs\MessageView;
use Src\Domain\Chat\Repositories\ConversationRepository;
use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Chat\Repositories\MessageRepository;
use Src\Domain\Shared\NotAuthorized;
use Src\Domain\Shared\NotFound;

/** Pages a conversation backwards by message id (FR-08). */
final class FetchHistory
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly ConversationRepository $conversations,
        private readonly GroupRepository $groups,
    ) {}

    /** @return array{messages:array<int,array<string,mixed>>,next_before:?string} */
    public function execute(int $userId, bool $isGroup, int $conversationId, ?int $beforeId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        if ($isGroup) {
            $group = $this->groups->find($conversationId) ?? throw new NotFound('Group not found.');
            if (! $group->isMember($userId)) {
                throw new NotAuthorized('You are not a member of this group.');
            }
        } else {
            $participants = $this->conversations->participants($conversationId) ?? throw new NotFound('Conversation not found.');
            if (! in_array($userId, $participants, true)) {
                throw new NotAuthorized('Not your conversation.');
            }
        }

        $found = $this->messages->history($isGroup, $conversationId, $beforeId, $limit);
        $views = array_map(fn ($m) => MessageView::from($m), $found);
        $next = count($found) === $limit ? (string) end($found)->id : null;

        return ['messages' => $views, 'next_before' => $next];
    }
}
