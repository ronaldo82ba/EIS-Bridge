<?php

namespace Database\Seeders;

use App\Enums\BillingModel;
use App\Enums\BillingUnit;
use App\Models\LicensePlan;
use Illuminate\Database\Seeder;

class LicensePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Vendor One-Time License',
                'slug' => 'vendor_one_time',
                'billing_model' => BillingModel::OneTime,
                'unit' => BillingUnit::Vendor,
                'amount' => 50000.00,
            ],
            [
                'name' => 'Vendor Per-Merchant Activation',
                'slug' => 'vendor_per_merchant',
                'billing_model' => BillingModel::PerUnit,
                'unit' => BillingUnit::Merchant,
                'amount' => 2500.00,
            ],
            [
                'name' => 'Vendor Monthly Hosting',
                'slug' => 'vendor_monthly_hosting',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Vendor,
                'amount' => 15000.00,
            ],
            [
                'name' => 'Merchant One-Time License',
                'slug' => 'merchant_one_time',
                'billing_model' => BillingModel::OneTime,
                'unit' => BillingUnit::Merchant,
                'amount' => 10000.00,
            ],
            [
                'name' => 'Merchant Per-Branch Monthly',
                'slug' => 'merchant_per_branch_monthly',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Branch,
                'amount' => 500.00,
            ],
            [
                'name' => 'SaaS Per Merchant Monthly',
                'slug' => 'saas_per_merchant_monthly',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Merchant,
                'amount' => 999.00,
            ],
            [
                'name' => 'SaaS Per Branch Monthly',
                'slug' => 'saas_per_branch_monthly',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Branch,
                'amount' => 199.00,
            ],
        ];

        foreach ($plans as $plan) {
            LicensePlan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'billing_model' => $plan['billing_model']->value,
                    'unit' => $plan['unit']->value,
                    'amount' => $plan['amount'],
                    'currency' => 'PHP',
                    'is_active' => true,
                ],
            );
        }
    }
}
