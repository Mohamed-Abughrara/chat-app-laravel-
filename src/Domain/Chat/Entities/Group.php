<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Entities;

use Src\Domain\Shared\GroupLimitExceeded;
use Src\Domain\Shared\InvalidMessage;

final class Group
{
    public const MAX_MEMBERS = 100;

    /** @var int[] */
    private array $memberIds;

    /** @param int[] $memberIds */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly int $ownerId,
        array $memberIds,
    ) {
        if (trim($name) === '') {
            throw new InvalidMessage('Group name is required.');
        }
        $this->memberIds = array_values(array_unique(array_map('intval', [...$memberIds, $ownerId])));
        $this->assertWithinLimit();
    }

    /** @return int[] */
    public function memberIds(): array
    {
        return $this->memberIds;
    }

    public function isMember(int $userId): bool
    {
        return in_array($userId, $this->memberIds, true);
    }

    public function addMember(int $userId): void
    {
        if ($this->isMember($userId)) {
            return;
        }
        $this->memberIds[] = $userId;
        $this->assertWithinLimit();
    }

    public function removeMember(int $userId): void
    {
        if ($userId === $this->ownerId) {
            throw new InvalidMessage('The owner cannot be removed from the group.');
        }
        $this->memberIds = array_values(array_filter($this->memberIds, fn (int $m) => $m !== $userId));
    }

    private function assertWithinLimit(): void
    {
        if (count($this->memberIds) > self::MAX_MEMBERS) {
            throw new GroupLimitExceeded('A group holds at most '.self::MAX_MEMBERS.' members.');
        }
    }
}
