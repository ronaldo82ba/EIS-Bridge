<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\Merchant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnalyticsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $eventType = 'status_change',
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('analytics'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'analytics.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $merchant = $this->invoice->relationLoaded('merchant')
            ? $this->invoice->merchant
            : Merchant::query()->where('merchant_code', $this->invoice->merchant_code)->first();

        return [
            'invoice_id' => $this->invoice->id,
            'merchant_id' => $merchant?->id,
            'merchant_code' => $this->invoice->merchant_code,
            'vendor_id' => $merchant?->vendor_id,
            'processing_status' => $this->invoice->processing_status,
            'eis_status' => $this->invoice->eis_status,
            'created_at' => $this->invoice->created_at?->toIso8601String(),
            'event_type' => $this->eventType,
        ];
    }
}
