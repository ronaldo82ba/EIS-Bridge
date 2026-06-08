<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Alerts\AlertEmitter;
use Tests\TestCase;

class AlertEmitterTest extends TestCase
{
    public function test_processing_failure_creates_open_alert(): void
    {
        $vendor = Vendor::create([
            'name' => 'Emitter Vendor',
            'api_key' => hash('sha256', 'emitter-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'EMIT-001',
            'name' => 'Emitter Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-EMIT-001',
            'transaction_id' => 'TX-001',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'failed',
            'raw_pos_json' => ['test' => true],
        ]);

        $alert = AlertEmitter::processingFailure(
            $invoice,
            'Signing failed for invoice BRG-EMIT-001',
            ['message' => 'Certificate not found']
        );

        $this->assertNotNull($alert);
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'category' => Alert::CATEGORY_PROCESSING,
            'invoice_id' => $invoice->id,
            'merchant_id' => $merchant->id,
            'vendor_id' => $vendor->id,
        ]);
        $this->assertNull($alert->resolved_at);
        $this->assertSame('open', $alert->status);
    }

    public function test_processing_failure_dedupes_within_one_hour(): void
    {
        $vendor = Vendor::create([
            'name' => 'Dedupe Vendor',
            'api_key' => hash('sha256', 'dedupe-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'DED-001',
            'name' => 'Dedupe Merchant',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-DED-001',
            'transaction_id' => 'TX-DED',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'failed',
            'raw_pos_json' => ['test' => true],
        ]);

        AlertEmitter::processingFailure($invoice, 'First failure');
        $second = AlertEmitter::processingFailure($invoice, 'Second failure');

        $this->assertNull($second);
        $this->assertDatabaseCount('alerts', 1);
    }
}
