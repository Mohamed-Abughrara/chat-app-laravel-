<?php

declare(strict_types=1);

namespace Src\Infrastructure\Discovery;

use Src\Application\Ports\ServerRegistry;

/** Config-driven stand-in for ZooKeeper discovery. */
final class StaticServerRegistry implements ServerRegistry
{
    /**
     * @param  array<int,array{host:string,port:int}>  $chatServers
     * @param  array{host:string,port:int}  $presenceServer
     */
    public function __construct(private readonly array $chatServers, private readonly array $presenceServer) {}

    public function assignChatServer(): array
    {
        return $this->chatServers[array_rand($this->chatServers)];
    }

    public function presenceServer(): array
    {
        return $this->presenceServer;
    }
}
