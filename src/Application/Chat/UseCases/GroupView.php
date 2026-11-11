<?php

declare(strict_types=1);

namespace Src\Application\Chat\UseCases;

use Src\Domain\Chat\Entities\Group;

final class GroupView
{
    /** @return array{id:int,name:string,owner_id:int,member_ids:int[]} */
    public static function from(Group $g): array
    {
        return ['id' => (int) $g->id, 'name' => $g->name, 'owner_id' => $g->ownerId, 'member_ids' => $g->memberIds()];
    }
}
