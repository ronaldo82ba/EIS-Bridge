<?php

namespace Tests\Feature;

use App\Events\AlertCreated;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Alerts\AlertEmitter;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AlertCreatedBroadcastTest extends TestCase
{
    public function test_alert_created_event_dispatches_when_emitter_creates_alert(): void
    {
        Event::fake([AlertCreated::class]);

        $vendor = Vendor::create([
            'name' => 'Broadcast Vendor',
            'api_key' => hash('sha256', 'broadcast-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'BCAST-001',
            'name' => 'Broadcast Merchant',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-BCAST-001',
            'transaction_id' => 'TX-BCAST',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => '001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'failed',
            'raw_pos_json' => ['test' => true],
        ]);

        $alert = AlertEmitter::processingFailure($invoice, 'Broadcast test failure');

        $this->assertNotNull($alert);

        Event::assertDispatched(AlertCreated::class, function (AlertCreated $event) use ($alert) {
            return $event->alert->id === $alert->id;
        });
    }

    public function test_alert_created_event_not_dispatched_for_deduped_alert(): void
    {
        Event::fake([AlertCreated::class]);

        $vendor = Vendor::create([
            'name' => 'Dedupe Broadcast Vendor',
            'api_key' => hash('sha256', 'dedupe-broadcast-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'DBCAST-001',
            'name' => 'Dedupe Broadcast Merchant',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-DBCAST-001',
            'transaction_id' => 'TX-DBCAST',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => '001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'failed',
            'raw_pos_json' => ['test' => true],
        ]);

        AlertEmitter::processingFailure($invoice, 'First failure');
        AlertEmitter::processingFailure($invoice, 'Second failure');

        Event::assertDispatchedTimes(AlertCreated::class, 1);
    }
}
