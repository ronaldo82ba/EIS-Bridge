<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Branch;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Billing\SaasBillingService;
use Database\Seeders\LicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_monthly_total_for_merchants_and_branches(): void
    {
        $this->seed(LicensePlanSeeder::class);

        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'api_key' => 'TEST_VENDOR_KEY',
            'status' => 'active',
        ]);

        for ($m = 1; $m <= 3; $m++) {
            $merchant = Merchant::create([
                'vendor_id' => $vendor->id,
                'merchant_code' => "MRC{$m}",
                'name' => "Merchant {$m}",
                'status' => 'active',
            ]);

            for ($b = 1; $b <= 2; $b++) {
                Branch::create([
                    'merchant_id' => $merchant->id,
                    'branch_code' => "BR{$m}{$b}",
                    'name' => "Branch {$m}-{$b}",
                    'status' => 'active',
                ]);
            }
        }

        $service = app(SaasBillingService::class);
        $result = $service->calculateMonthlyCharges($vendor);

        $this->assertSame(3, $result['merchant_count']);
        $this->assertSame(6, $result['branch_count']);
        $this->assertSame(999.0, $result['merchant_unit_amount']);
        $this->assertSame(199.0, $result['branch_unit_amount']);
        $this->assertSame(2997.0, $result['merchant_charge']);
        $this->assertSame(1194.0, $result['branch_charge']);
        $this->assertSame(4191.0, $result['total']);
    }
}
