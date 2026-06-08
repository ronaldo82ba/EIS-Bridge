<?php

namespace Tests\Feature;

use App\Events\AnalyticsUpdated;
use App\Events\InvoiceStatusUpdated;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Support\InvoiceBroadcaster;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnalyticsUpdatedBroadcastTest extends TestCase
{
    public function test_status_updated_dispatches_analytics_updated_event(): void
    {
        Event::fake([AnalyticsUpdated::class, InvoiceStatusUpdated::class]);

        $vendor = Vendor::create([
            'name' => 'Analytics Vendor',
            'api_key' => hash('sha256', 'analytics-broadcast-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'ANLY-001',
            'name' => 'Analytics Merchant',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-ANLY-001',
            'transaction_id' => 'TX-ANLY',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => '001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'mapped',
            'eis_status' => 'acknowledged',
            'raw_pos_json' => ['test' => true],
        ]);

        InvoiceBroadcaster::statusUpdated($invoice);

        Event::assertDispatched(InvoiceStatusUpdated::class, function (InvoiceStatusUpdated $event) use ($invoice) {
            return $event->invoice->id === $invoice->id;
        });

        Event::assertDispatched(AnalyticsUpdated::class, function (AnalyticsUpdated $event) use ($invoice, $merchant, $vendor) {
            $payload = $event->broadcastWith();

            return $event->eventType === 'status_change'
                && $payload['invoice_id'] === $invoice->id
                && $payload['merchant_id'] === $merchant->id
                && $payload['merchant_code'] === $merchant->merchant_code
                && $payload['vendor_id'] === $vendor->id
                && $payload['processing_status'] === 'mapped'
                && $payload['eis_status'] === 'acknowledged'
                && $payload['event_type'] === 'status_change'
                && $payload['created_at'] !== null;
        });
    }

    public function test_created_dispatches_analytics_updated_with_new_invoice_type(): void
    {
        Event::fake([AnalyticsUpdated::class]);

        $vendor = Vendor::create([
            'name' => 'New Invoice Vendor',
            'api_key' => hash('sha256', 'new-invoice-broadcast-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'NEW-001',
            'name' => 'New Invoice Merchant',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-NEW-001',
            'transaction_id' => 'TX-NEW',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => '001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'queued',
            'raw_pos_json' => ['test' => true],
        ]);

        InvoiceBroadcaster::created($invoice);

        Event::assertDispatched(AnalyticsUpdated::class, function (AnalyticsUpdated $event) use ($invoice) {
            return $event->eventType === 'new_invoice'
                && $event->broadcastWith()['event_type'] === 'new_invoice'
                && $event->invoice->id === $invoice->id;
        });
    }
}
