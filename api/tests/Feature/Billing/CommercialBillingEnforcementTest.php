<?php

namespace Tests\Feature\Billing;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\MerchantDailyVolume;
use App\Models\Vendor;
use App\Services\Billing\DailyVolumeMeter;
use App\Services\Billing\MerchantLicenseService;
use App\Services\Billing\PrepaidWalletService;
use App\Services\TransactionProcessor;
use Carbon\Carbon;
use Database\Seeders\LicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialBillingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LicensePlanSeeder::class);
        $this->seedMerchantGraph();
    }

    public function test_lite_wallet_blocks_when_balance_is_zero_then_allows_after_recharge(): void
    {
        app(MerchantLicenseService::class)->assign($this->merchant, 'lite_setup');

        $processor = app(TransactionProcessor::class);

        $blocked = $processor->processSingle($this->samplePayload('POS-LITE-001'), $this->vendor);
        $this->assertSame('rejected', $blocked['status']);
        $this->assertSame('insufficient_prepaid_balance', $blocked['error']);
        $this->assertSame(0.0, (float) $blocked['details']['balance']);

        app(PrepaidWalletService::class)->credit($this->merchant->fresh(), 2.00, 'test_recharge');

        $first = $processor->processSingle($this->samplePayload('POS-LITE-001'), $this->vendor);
        $this->assertSame('accepted', $first['status']);
        $this->assertSame(1.0, app(PrepaidWalletService::class)->balance($this->merchant->fresh()));

        $second = $processor->processSingle($this->samplePayload('POS-LITE-002'), $this->vendor);
        $this->assertSame('accepted', $second['status']);
        $this->assertSame(0.0, app(PrepaidWalletService::class)->balance($this->merchant->fresh()));

        $third = $processor->processSingle($this->samplePayload('POS-LITE-003'), $this->vendor);
        $this->assertSame('rejected', $third['status']);
        $this->assertSame('insufficient_prepaid_balance', $third['error']);
    }

    public function test_postpaid_tier_1500_blocks_over_800_daily(): void
    {
        app(MerchantLicenseService::class)->assign($this->merchant, 'postpaid_tier_1500');

        $usageDate = app(DailyVolumeMeter::class)->manilaToday();
        MerchantDailyVolume::create([
            'merchant_id' => $this->merchant->id,
            'usage_date' => $usageDate,
            'invoice_count' => 800,
        ]);

        $result = app(TransactionProcessor::class)->processSingle(
            $this->samplePayload('POS-CAP-1500'),
            $this->vendor,
        );

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('daily_volume_cap_exceeded', $result['error']);
        $this->assertSame(800, $result['details']['cap']);
        $this->assertSame('postpaid_tier_1500', $result['details']['plan']);
    }

    public function test_postpaid_tier_1500_allows_under_cap(): void
    {
        app(MerchantLicenseService::class)->assign($this->merchant, 'postpaid_tier_1500');

        $usageDate = app(DailyVolumeMeter::class)->manilaToday();
        MerchantDailyVolume::create([
            'merchant_id' => $this->merchant->id,
            'usage_date' => $usageDate,
            'invoice_count' => 799,
        ]);

        $result = app(TransactionProcessor::class)->processSingle(
            $this->samplePayload('POS-UNDER-1500'),
            $this->vendor,
        );

        $this->assertSame('accepted', $result['status']);
        $this->assertSame(800, app(DailyVolumeMeter::class)->currentCount($this->merchant->fresh(), $usageDate));
    }

    public function test_postpaid_tier_2500_blocks_over_3000_daily(): void
    {
        app(MerchantLicenseService::class)->assign($this->merchant, 'postpaid_tier_2500');

        $usageDate = app(DailyVolumeMeter::class)->manilaToday();
        MerchantDailyVolume::create([
            'merchant_id' => $this->merchant->id,
            'usage_date' => $usageDate,
            'invoice_count' => 3000,
        ]);

        $result = app(TransactionProcessor::class)->processSingle(
            $this->samplePayload('POS-CAP-2500'),
            $this->vendor,
        );

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('daily_volume_cap_exceeded', $result['error']);
        $this->assertSame(3000, $result['details']['cap']);
        $this->assertSame('postpaid_tier_2500', $result['details']['plan']);
    }

    public function test_manila_day_boundary_uses_separate_counters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 23:30:00', 'Asia/Manila'));
        app(MerchantLicenseService::class)->assign($this->merchant, 'postpaid_tier_1500');
        $meter = app(DailyVolumeMeter::class);
        MerchantDailyVolume::create([
            'merchant_id' => $this->merchant->id,
            'usage_date' => '2026-08-16',
            'invoice_count' => 800,
        ]);

        $blocked = app(TransactionProcessor::class)->processSingle(
            $this->samplePayload('POS-MANILA-EVE'),
            $this->vendor,
        );
        $this->assertSame('daily_volume_cap_exceeded', $blocked['error']);

        Carbon::setTestNow(Carbon::parse('2026-08-17 00:05:00', 'Asia/Manila'));
        $allowed = app(TransactionProcessor::class)->processSingle(
            $this->samplePayload('POS-MANILA-NEXT'),
            $this->vendor,
        );
        $this->assertSame('accepted', $allowed['status']);
        $this->assertSame(1, $meter->currentCount($this->merchant->fresh(), '2026-08-17'));

        Carbon::setTestNow();
    }

    public function test_monthly_postpaid_fees_included_in_merchant_calculation(): void
    {
        app(MerchantLicenseService::class)->assign($this->merchant, 'postpaid_tier_1500');

        $fees = app(MerchantLicenseService::class)->calculateMonthlyMerchantFees($this->merchant->fresh());

        $this->assertSame(1500.0, $fees['total']);
        $this->assertSame('postpaid_tier_1500', $fees['line_items'][0]['plan_slug']);
    }

    private function seedMerchantGraph(): void
    {
        $this->vendor = Vendor::create([
            'name' => 'Commercial Vendor',
            'api_key' => hash('sha256', 'commercial-key'),
            'status' => 'active',
        ]);

        $this->merchant = Merchant::create([
            'vendor_id' => $this->vendor->id,
            'merchant_code' => 'MRC-COMM',
            'name' => 'Commercial Merchant',
            'tin' => '123-456-789-000',
            'status' => 'active',
            'prepaid_wallet_balance' => 0,
        ]);

        $branch = Branch::create([
            'merchant_id' => $this->merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'status' => 'active',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal 01',
            'status' => 'active',
        ]);
    }

    private function samplePayload(string $transactionId): array
    {
        return [
            'transaction_id' => $transactionId,
            'transaction_datetime' => '2026-08-16T14:23:55+08:00',
            'merchant_code' => 'MRC-COMM',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'invoice_type' => 'OR',
            'currency' => 'PHP',
            'items' => [
                [
                    'line_no' => 1,
                    'sku' => 'SKU001',
                    'description' => 'Product A',
                    'qty' => 1,
                    'unit_price' => 100.0,
                    'discount' => 0.0,
                    'vat_rate' => 12.0,
                ],
            ],
            'totals' => [
                'gross' => 100.0,
                'discount' => 0.0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0.0,
                'zero_rated_sales' => 0.0,
                'service_charge' => 0.0,
                'net' => 100.0,
            ],
            'payment' => [
                'method' => 'CASH',
                'amount' => 100.0,
            ],
        ];
    }
}
