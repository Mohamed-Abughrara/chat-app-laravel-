<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Src\Domain\Chat\Entities\Group;
use Src\Domain\Chat\Repositories\GroupRepository;

final class EloquentGroupRepository implements GroupRepository
{
    public function save(Group $group): Group
    {
        return DB::transaction(function () use ($group) {
            $members = $group->memberIds();

            if ($group->id === null) {
                $id = (int) DB::table('groups')->insertGetId([
                    'name' => $group->name,
                    'owner_id' => $group->ownerId,
                    'member_count' => count($members),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $group->id;
                DB::table('groups')->where('id', $id)->update(['member_count' => count($members), 'updated_at' => now()]);
            }

            $existing = DB::table('group_members')->where('group_id', $id)->pluck('user_id')->map(fn ($v) => (int) $v)->all();
            $toAdd = array_diff($members, $existing);
            $toRemove = array_diff($existing, $members);

            foreach ($toAdd as $uid) {
                DB::table('group_members')->insert([
                    'group_id' => $id,
                    'user_id' => $uid,
                    'role' => $uid === $group->ownerId ? 'owner' : 'member',
                    'joined_at' => now(),
                ]);
            }
            if ($toRemove !== []) {
                DB::table('group_members')->where('group_id', $id)->whereIn('user_id', $toRemove)->delete();
            }

            return new Group($id, $group->name, $group->ownerId, $members);
        });
    }

    public function find(int $id): ?Group
    {
        $row = DB::table('groups')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        $members = DB::table('group_members')->where('group_id', $id)->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        return new Group((int) $row->id, $row->name, (int) $row->owner_id, $members);
    }

    public function forUser(int $userId): array
    {
        $ids = DB::table('group_members')->where('user_id', $userId)->pluck('group_id');

        return $ids->map(fn ($id) => $this->find((int) $id))->filter()->values()->all();
    }
}
