<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\NotAuthorized;
use Src\Domain\Shared\NotFound;

final class ChangeGroupMembers
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly UserRepository $users,
    ) {}

    /**
     * @param  int[]  $add
     * @param  int[]  $remove
     * @return array{id:int,name:string,owner_id:int,member_ids:int[]}
     */
    public function execute(int $actorId, int $groupId, array $add = [], array $remove = []): array
    {
        $group = $this->groups->find($groupId) ?? throw new NotFound('Group not found.');
        if ($group->ownerId !== $actorId) {
            throw new NotAuthorized('Only the group owner can change members.');
        }

        foreach ($add as $id) {
            if ($this->users->find($id) === null) {
                throw new NotFound("User $id not found.");
            }
            $group->addMember($id);
        }
        foreach ($remove as $id) {
            $group->removeMember($id);
        }

        return GroupView::from($this->groups->save($group));
    }
}
