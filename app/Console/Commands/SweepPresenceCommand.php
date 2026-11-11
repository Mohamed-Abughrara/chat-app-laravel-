<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Application\Presence\SweepPresence;

class SweepPresenceCommand extends Command
{
    protected $signature = 'chat:sweep-presence';

    protected $description = 'Mark users offline whose heartbeat is older than the presence timeout';

    public function handle(SweepPresence $sweep): int
    {
        $expired = $sweep->execute();
        if ($expired !== []) {
            $this->info('Offline: '.implode(',', $expired));
        }

        return self::SUCCESS;
    }
}
