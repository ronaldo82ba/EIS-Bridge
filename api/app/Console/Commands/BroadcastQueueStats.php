<?php

namespace App\Console\Commands;

use App\Services\Observability\QueueBroadcaster;
use App\Services\Observability\QueueMonitor;
use Illuminate\Console\Command;

class BroadcastQueueStats extends Command
{
    protected $signature = 'queues:broadcast';

    protected $description = 'Broadcast queue depth updates when changed or every 30 seconds';

    public function handle(QueueMonitor $monitor, QueueBroadcaster $broadcaster): int
    {
        $broadcast = $broadcaster->broadcastIfChanged($monitor);

        if ($broadcast) {
            $this->info('Queue stats broadcast.');
        }

        return self::SUCCESS;
    }
}
