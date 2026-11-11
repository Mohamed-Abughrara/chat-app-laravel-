<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Domain\Chat\Entities\Group;
use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\NotFound;

final class CreateGroup
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly UserRepository $users,
    ) {}

    /**
     * @param  int[]  $memberIds
     * @return array{id:int,name:string,owner_id:int,member_ids:int[]}
     */
    public function execute(int $ownerId, string $name, array $memberIds): array
    {
        foreach ($memberIds as $id) {
            if ($this->users->find($id) === null) {
                throw new NotFound("User $id not found.");
            }
        }

        $group = $this->groups->save(new Group(null, $name, $ownerId, $memberIds));

        return GroupView::from($group);
    }
}
