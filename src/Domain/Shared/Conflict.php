<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class Conflict extends DomainError
{
    public function errorCode(): string
    {
        return 'conflict';
    }
}
