<?php

namespace App\Events;

use App\Models\Alert;
use App\Support\AlertPresenter;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Alert $alert,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alerts.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->alert->loadMissing([
            'merchant:id,name',
            'vendor:id,name',
            'invoice:id,bridge_transaction_id',
            'certificate:id,merchant_id,filename',
        ]);

        return AlertPresenter::transform($this->alert);
    }
}
