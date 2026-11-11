<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class InvalidMessage extends DomainError
{
    public function errorCode(): string
    {
        return 'invalid_message';
    }
}
