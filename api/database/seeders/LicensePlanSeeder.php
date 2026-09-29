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
        // Commercial sales truth: docs/commercial-pricing.md (supersedes legacy amounts for quotes).
        // Legacy vendor/merchant/saas rows kept for existing tests and admin flows.
        $plans = [
            // --- Commercial (locked) ---
            // Vendor channel: ₱350k one-time includes distributorship; merchants still pay store/Lite/postpaid SKUs.
            [
                'name' => 'Vendor / Distributor Setup (incl. Distributorship)',
                'slug' => 'vendor_distributor_setup_350k',
                'billing_model' => BillingModel::OneTime,
                'unit' => BillingUnit::Vendor,
                'amount' => 350000.00,
            ],
            [
                'name' => 'EIS Bridge Lite Setup',
                'slug' => 'lite_setup',
                'billing_model' => BillingModel::OneTime,
                'unit' => BillingUnit::Merchant,
                'amount' => 17000.00,
            ],
            // Prepaid wallet: no wallet enum; per_unit @ ₱1/upload. Typical load e.g. qty 3000 = ₱3,000.
            [
                'name' => 'EIS Bridge Lite Prepaid Wallet (₱1/upload)',
                'slug' => 'lite_prepaid_wallet',
                'billing_model' => BillingModel::PerUnit,
                'unit' => BillingUnit::Merchant,
                'amount' => 1.00,
            ],
            [
                'name' => 'Store / Merchant Activation',
                'slug' => 'store_activation_35k',
                'billing_model' => BillingModel::OneTime,
                'unit' => BillingUnit::Merchant,
                'amount' => 35000.00,
            ],
            [
                'name' => 'Postpaid Standard (≤800 e-invoices/day)',
                'slug' => 'postpaid_tier_1500',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Merchant,
                'amount' => 1500.00,
            ],
            [
                'name' => 'Postpaid High Volume (up to 3,000 e-invoices/day)',
                'slug' => 'postpaid_tier_2500',
                'billing_model' => BillingModel::RecurringMonthly,
                'unit' => BillingUnit::Merchant,
                'amount' => 2500.00,
            ],
            // --- Legacy (tests / older catalog) ---
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
