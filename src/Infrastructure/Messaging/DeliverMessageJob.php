<?php

declare(strict_types=1);

namespace Src\Infrastructure\Messaging;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\Chat\UseCases\DeliverMessage;
use Src\Domain\Chat\Events\MessageSent;

/** History-writer + delivery + notification consumer. After 5 failures it lands in failed_jobs (dead letter). */
final class DeliverMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var int[] */
    public array $backoff = [1, 3, 10, 30];

    public function __construct(public readonly MessageSent $event) {}

    public function handle(DeliverMessage $deliver): void
    {
        $deliver->execute($this->event);
    }
}
