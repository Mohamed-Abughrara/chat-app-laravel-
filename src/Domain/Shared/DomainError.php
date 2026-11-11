<?php

declare(strict_types=1);

namespace Src\Domain\Shared;

use RuntimeException;

/** Base class for all business-rule violations. errorCode() is a stable machine-readable string. */
abstract class DomainError extends RuntimeException
{
    abstract public function errorCode(): string;

    public function retryable(): bool
    {
        return false;
    }
}
