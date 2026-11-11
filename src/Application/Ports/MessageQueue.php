<?php

declare(strict_types=1);

namespace Src\Application\Ports;

use Src\Domain\Chat\Events\MessageSent;

/** Durable sync queue between accept and persist/deliver (Kafka in production, DB queue locally). */
interface MessageQueue
{
    public function publish(MessageSent $event): void;
}
