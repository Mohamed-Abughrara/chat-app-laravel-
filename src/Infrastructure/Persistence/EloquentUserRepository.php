<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Domain\Identity\User;
use Src\Domain\Identity\UserRepository;

final class EloquentUserRepository implements UserRepository
{
    public function create(string $name, string $email, string $passwordHash): User
    {
        $id = (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new User($id, $name, $email, $passwordHash);
    }

    public function find(int $id): ?User
    {
        return $this->hydrate(DB::table('users')->where('id', $id)->first());
    }

    public function findByEmail(string $email): ?User
    {
        return $this->hydrate(DB::table('users')->where('email', $email)->first());
    }

    public function update(int $id, string $name): void
    {
        DB::table('users')->where('id', $id)->update(['name' => $name, 'updated_at' => now()]);
    }

    public function friends(int $userId): array
    {
        return DB::table('friendships')
            ->join('users', 'users.id', '=', 'friendships.friend_id')
            ->where('friendships.user_id', $userId)
            ->orderBy('users.name')
            ->select('users.*')
            ->get()
            ->map(fn ($r) => $this->hydrate($r))
            ->all();
    }

    public function friendIds(int $userId): array
    {
        return DB::table('friendships')->where('user_id', $userId)->pluck('friend_id')->map(fn ($v) => (int) $v)->all();
    }

    public function addFriend(int $userId, int $friendId): void
    {
        DB::table('friendships')->insertOrIgnore([
            ['user_id' => $userId, 'friend_id' => $friendId],
            ['user_id' => $friendId, 'friend_id' => $userId],
        ]);
    }

    private function hydrate(?object $r): ?User
    {
        return $r === null ? null : new User((int) $r->id, $r->name, $r->email, $r->password);
    }
}
