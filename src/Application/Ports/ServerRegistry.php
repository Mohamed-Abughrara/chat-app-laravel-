<?php

declare(strict_types=1);

namespace Src\Application\Ports;

interface ServerRegistry
{
    /** @return array{host:string,port:int} least-loaded live chat server */
    public function assignChatServer(): array;

    /** @return array{host:string,port:int} */
    public function presenceServer(): array;
}
