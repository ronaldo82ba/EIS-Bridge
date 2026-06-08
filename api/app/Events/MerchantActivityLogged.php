<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{type: string, created_at: string, details: array<string, mixed>, invoice_id?: int}  $event
     */
    public function __construct(
        public int $merchantId,
        public array $event,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('merchants.'.$this->merchantId.'.activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'merchant.activity';
    }

    /**
     * @return array{event: array{type: string, created_at: string, details: array<string, mixed>, invoice_id?: int}}
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
        ];
    }
}
