<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, array{waiting: int, processing: int}>  $queues
     */
    public function __construct(
        public array $queues,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('queues'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queues.updated';
    }

    /**
     * @return array{queues: array<string, array{waiting: int, processing: int}>}
     */
    public function broadcastWith(): array
    {
        return [
            'queues' => $this->queues,
        ];
    }
}
