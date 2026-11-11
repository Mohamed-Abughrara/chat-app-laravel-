<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Repositories;

use Src\Domain\Chat\Entities\Group;

interface GroupRepository
{
    /** Persists the group (insert when id is null) and returns it with its id. */
    public function save(Group $group): Group;

    public function find(int $id): ?Group;

    /** @return Group[] */
    public function forUser(int $userId): array;
}
