<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class NotAuthorized extends DomainError
{
    public function errorCode(): string
    {
        return 'not_authorized';
    }
}
