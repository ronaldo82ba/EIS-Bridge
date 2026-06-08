<?php

namespace Tests\Unit;

use App\Events\QueueUpdated;
use Tests\TestCase;

class QueueUpdatedEventTest extends TestCase
{
    public function test_queue_updated_serializes_queues_array(): void
    {
        $queues = [
            'mapping' => ['waiting' => 3, 'processing' => 1],
            'signing' => ['waiting' => 0, 'processing' => 2],
            'transmission' => ['waiting' => 5, 'processing' => 0],
        ];

        $event = new QueueUpdated($queues);

        $this->assertSame('queues.updated', $event->broadcastAs());
        $this->assertSame(['queues' => $queues], $event->broadcastWith());
        $this->assertSame('queues', $event->broadcastOn()[0]->name);
    }
}
