<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('invoices.'.$this->invoice->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'invoice.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->invoice->id,
            'bridge_transaction_id' => $this->invoice->bridge_transaction_id,
            'processing_status' => $this->invoice->processing_status,
            'eis_status' => $this->invoice->eis_status,
            'eis_reference_no' => $this->invoice->eis_reference_no,
            'bir_json' => $this->invoice->bir_json !== null,
            'signed_json' => $this->invoice->signed_json !== null,
            'updated_at' => $this->invoice->updated_at?->toIso8601String(),
        ];
    }
}
