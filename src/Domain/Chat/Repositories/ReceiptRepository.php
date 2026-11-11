<?php

declare(strict_types=1);

namespace Src\Domain\Chat\Repositories;

interface ReceiptRepository
{
    public function markDelivered(int $messageId, int $userId): void;
}
