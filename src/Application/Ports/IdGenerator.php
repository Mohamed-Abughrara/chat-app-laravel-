<?php

declare(strict_types=1);

namespace Src\Application\Ports;

interface IdGenerator
{
    /** Next unique, time-sortable 64-bit id. */
    public function next(): int;
}
