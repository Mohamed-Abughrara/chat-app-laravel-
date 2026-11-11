<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class GroupLimitExceeded extends DomainError
{
    public function errorCode(): string
    {
        return 'group_limit_exceeded';
    }
}
