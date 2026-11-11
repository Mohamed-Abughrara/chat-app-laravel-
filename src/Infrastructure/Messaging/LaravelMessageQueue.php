<?php

declare(strict_types=1);

namespace Src\Infrastructure\Messaging;

use Src\Application\Ports\MessageQueue;
use Src\Domain\Chat\Events\MessageSent;

/** Local stand-in for the Kafka sync queue: a durable Laravel queue (database driver). */
final class LaravelMessageQueue implements MessageQueue
{
    public function publish(MessageSent $event): void
    {
        DeliverMessageJob::dispatch($event);
    }
}
