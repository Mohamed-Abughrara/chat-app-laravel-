<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class NotFound extends DomainError
{
    public function errorCode(): string
    {
        return 'not_found';
    }
}
