<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

final class InvalidCredentials extends DomainError
{
    public function errorCode(): string
    {
        return 'invalid_credentials';
    }
}
