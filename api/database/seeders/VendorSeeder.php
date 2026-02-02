<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = env('SANDBOX_API_KEY', 'VENDOR_API_KEY_123');

        $vendor = Vendor::firstOrCreate(
            ['api_key' => $apiKey],
            ['name' => 'Sandbox Vendor']
        );

        $merchant = Merchant::firstOrCreate(
            ['vendor_id' => $vendor->id, 'merchant_code' => 'MRC123'],
            ['name' => 'Sandbox Merchant']
        );

        $branch = Branch::firstOrCreate(
            ['merchant_id' => $merchant->id, 'branch_code' => 'BR001'],
            ['name' => 'Main Branch']
        );

        Device::firstOrCreate(
            ['branch_id' => $branch->id, 'pos_device_id' => 'POS01'],
            ['name' => 'POS Terminal 01']
        );
    }
}
