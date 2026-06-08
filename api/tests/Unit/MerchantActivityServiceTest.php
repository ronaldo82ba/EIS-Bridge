<?php

namespace Tests\Unit;

use App\Models\CertificateAlert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Activity\MerchantActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MerchantActivityServiceTest extends TestCase
{
    private function seedMerchant(string $code = 'MRC-ACT-001'): array
    {
        $vendor = Vendor::create([
            'name' => 'Activity Vendor',
            'api_key' => hash('sha256', 'activity-vendor-'.$code),
            'status' => 'active',
            'webhook_url' => 'https://example.test/webhook',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $code,
            'name' => 'Activity Merchant',
            'tin' => '111-222-333-000',
            'address' => '1 Activity St',
            'status' => 'active',
        ]);

        return [$vendor, $merchant];
    }

    public function test_returns_events_sorted_newest_first(): void
    {
        [, $merchant] = $this->seedMerchant();

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-OLD',
            'transaction_id' => 'POS-OLD',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-OLD'],
            'processing_status' => 'mapped',
        ]);
        $invoice->forceFill([
            'created_at' => Carbon::parse('2026-06-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-06-01 10:00:00'),
        ])->save();

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapped',
            'timestamp' => Carbon::parse('2026-06-01 10:05:00'),
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'signed',
            'timestamp' => Carbon::parse('2026-06-01 10:10:00'),
        ]);

        $newInvoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-NEW',
            'transaction_id' => 'POS-NEW',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-NEW'],
            'processing_status' => 'queued',
        ]);
        $newInvoice->forceFill([
            'created_at' => Carbon::parse('2026-06-02 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-02 09:00:00'),
        ])->save();

        WebhookDelivery::create([
            'vendor_id' => $merchant->vendor_id,
            'invoice_id' => $newInvoice->id,
            'event' => 'transaction.eis_acknowledged',
            'request_url' => 'https://example.test/webhook',
            'attempt' => 1,
            'status_code' => 200,
            'success' => true,
            'delivered_at' => Carbon::parse('2026-06-02 09:30:00'),
        ]);

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => Crypt::encryptString('certificates/'.$merchant->id.'/cert.pfx'),
            'password_encrypted' => Crypt::encryptString('secret'),
            'expires_at' => now()->addDays(5),
        ]);

        $alert = CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
            'notified_admin' => true,
            'notified_vendor' => false,
        ]);
        $alert->forceFill([
            'created_at' => Carbon::parse('2026-06-02 08:00:00'),
            'updated_at' => Carbon::parse('2026-06-02 08:00:00'),
        ])->save();

        $service = app(MerchantActivityService::class);
        $paginator = $service->paginate($merchant, ['per_page' => 25]);

        $types = collect($paginator->items())->pluck('type')->all();
        $timestamps = collect($paginator->items())
            ->pluck('created_at')
            ->map(fn (string $value) => Carbon::parse($value)->timestamp)
            ->values()
            ->all();

        for ($index = 0; $index < count($timestamps) - 1; $index++) {
            $this->assertGreaterThanOrEqual($timestamps[$index + 1], $timestamps[$index]);
        }

        $this->assertContains('transaction_received', $types);
        $this->assertContains('mapping_completed', $types);
        $this->assertContains('signing_completed', $types);
        $this->assertContains('webhook_delivery', $types);
        $this->assertContains('certificate_alert', $types);
        $this->assertSame('webhook_delivery', $types[0]);
        $this->assertGreaterThanOrEqual(6, $paginator->total());
    }

    public function test_filters_by_event_group(): void
    {
        [, $merchant] = $this->seedMerchant('MRC-ACT-FILTER');

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-FILTER',
            'transaction_id' => 'POS-FILTER',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-FILTER'],
            'processing_status' => 'mapped',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapped',
            'timestamp' => now(),
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'signed',
            'timestamp' => now()->addMinute(),
        ]);

        $service = app(MerchantActivityService::class);
        $paginator = $service->paginate($merchant, ['type' => 'mapping', 'per_page' => 25]);

        $this->assertCount(1, $paginator->items());
        $this->assertSame('mapping_completed', $paginator->items()[0]['type']);
    }
}
