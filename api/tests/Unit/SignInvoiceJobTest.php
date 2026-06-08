<?php

namespace Tests\Unit;

use App\Jobs\SignInvoiceJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use App\Services\Certificate\TestCertificateGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignInvoiceJobTest extends TestCase
{
    public function test_signs_invoice_with_merchant_certificate(): void
    {
        config(['eis.sandbox_mode' => true]);

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

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
        ]);

        Device::create([
            'branch_id' => Branch::first()->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal 01',
        ]);

        $disk = (string) config('security.certificate_disk', 'local');
        $filename = 'test-merchant-'.$merchant->id.'.pfx';
        $relativePath = "certificates/test/{$filename}";
        $absolutePath = Storage::disk($disk)->path($relativePath);

        app(TestCertificateGenerator::class)->generate(
            $absolutePath,
            TestCertificateGenerator::DEFAULT_PASSWORD,
            'Sign Test Merchant'
        );

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => $filename,
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString(TestCertificateGenerator::DEFAULT_PASSWORD),
            'expires_at' => now()->addYear(),
        ]);

        $birJson = [
            'document_type' => 'OR',
            'transaction_id' => 'POS-999',
            'transaction_datetime' => now()->toIso8601String(),
            'currency' => 'PHP',
            'merchant' => ['code' => 'MRC123'],
            'branch' => ['code' => 'BR001'],
            'device' => ['pos_device_id' => 'POS01'],
            'line_items' => [[
                'line_no' => 1,
                'sku' => 'SKU001',
                'description' => 'Item',
                'quantity' => 1,
                'unit_price' => 100,
                'gross_amount' => 100,
            ]],
            'totals' => [
                'gross_amount' => 100,
                'discount_amount' => 0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0,
                'zero_rated_sales' => 0,
                'service_charge' => 0,
                'net_amount' => 100,
            ],
            'payment' => ['method' => 'CASH', 'amount' => 100],
            'eis_fields' => [
                'submission_version' => '1.0',
                'source' => 'EIS_BRIDGE',
            ],
        ];

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-TEST-001',
            'transaction_id' => 'POS-999',
            'merchant_code' => 'MRC123',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-999'],
            'bir_json' => $birJson,
            'processing_status' => 'mapped',
        ]);

        SignInvoiceJob::dispatchSync($invoice->id);

        $invoice->refresh();

        $this->assertContains($invoice->processing_status, ['signed', 'transmitting', 'sent']);
        $this->assertNotEmpty($invoice->signed_json);
        $this->assertArrayHasKey('signature', $invoice->signed_json);
        $this->assertArrayHasKey('signature_hash', $invoice->signed_json);
        $this->assertSame('RS256', $invoice->signed_json['algorithm']);
        $this->assertNotEmpty($invoice->signed_json['signature_hash']);
    }
}
