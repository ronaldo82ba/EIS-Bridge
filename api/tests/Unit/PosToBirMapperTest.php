<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Mapping\BirSchemaValidator;
use App\Services\Mapping\PosToBirMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosToBirMapperTest extends TestCase
{
    use RefreshDatabase;

    private function samplePosPayload(): array
    {
        return [
            'transaction_id' => 'POS-123456',
            'transaction_datetime' => '2026-06-07T14:23:55+08:00',
            'merchant_code' => 'MRC123',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'invoice_type' => 'OR',
            'currency' => 'PHP',
            'customer' => [
                'name' => 'Juan Dela Cruz',
                'tin' => '123-456-789-000',
            ],
            'items' => [
                [
                    'line_no' => 1,
                    'sku' => 'SKU001',
                    'description' => 'Product A',
                    'qty' => 2,
                    'unit_price' => 100.0,
                    'discount' => 0.0,
                    'vat_rate' => 12.0,
                ],
            ],
            'totals' => [
                'gross' => 200.0,
                'discount' => 0.0,
                'vatable_sales' => 178.57,
                'vat_amount' => 21.43,
                'vat_exempt_sales' => 0.0,
                'zero_rated_sales' => 0.0,
                'service_charge' => 0.0,
                'net' => 200.0,
            ],
            'payment' => [
                'method' => 'CASH',
                'amount' => 200.0,
            ],
        ];
    }

    public function test_maps_pos_sale_object_to_bir_structure(): void
    {
        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'api_key' => hash('sha256', 'test-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC123',
            'name' => 'Sandbox Merchant',
            'tin' => '123-456-789-000',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal 01',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-20260607-000001',
            'transaction_id' => 'POS-123456',
            'merchant_code' => 'MRC123',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => $this->samplePosPayload(),
            'processing_status' => 'queued',
        ]);

        $bir = app(PosToBirMapper::class)->map($invoice);
        app(BirSchemaValidator::class)->validate($bir);

        $this->assertSame('OR', $bir['document_type']);
        $this->assertSame('POS-123456', $bir['transaction_id']);
        $this->assertSame('MRC123', $bir['merchant']['code']);
        $this->assertSame('123-456-789-000', $bir['merchant']['tin']);
        $this->assertSame('BR001', $bir['branch']['code']);
        $this->assertSame('POS01', $bir['device']['pos_device_id']);
        $this->assertCount(1, $bir['line_items']);
        $this->assertSame(200.0, $bir['totals']['net_amount']);
        $this->assertSame('Juan Dela Cruz', $bir['customer']['buyer_name']);
        $this->assertSame('EB-20260607-000001', $bir['eis_fields']['bridge_transaction_id']);
        $this->assertSame('CASH', $bir['payment']['method']);
    }
}
