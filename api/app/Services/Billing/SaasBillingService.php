<?php

namespace App\Services\Billing;

use App\Models\Branch;
use App\Models\Merchant;
use App\Models\Vendor;

class SaasBillingService
{
    public function __construct(
        private readonly LicensePlanCatalog $catalog,
    ) {}

    public function calculateMonthlyCharges(?Vendor $vendor = null): array
    {
        $merchantQuery = Merchant::query();
        $branchQuery = Branch::query();

        if ($vendor) {
            $merchantQuery->where('vendor_id', $vendor->id);
            $branchQuery->whereHas('merchant', fn ($query) => $query->where('vendor_id', $vendor->id));
        }

        $merchantCount = $merchantQuery->count();
        $branchCount = $branchQuery->count();

        $merchantPlan = $this->catalog->findBySlug('saas_per_merchant_monthly');
        $branchPlan = $this->catalog->findBySlug('saas_per_branch_monthly');

        $merchantUnit = $merchantPlan ? (float) $merchantPlan->amount : 0.0;
        $branchUnit = $branchPlan ? (float) $branchPlan->amount : 0.0;

        $merchantCharge = round($merchantCount * $merchantUnit, 2);
        $branchCharge = round($branchCount * $branchUnit, 2);
        $total = round($merchantCharge + $branchCharge, 2);

        return [
            'merchant_count' => $merchantCount,
            'branch_count' => $branchCount,
            'merchant_unit_amount' => $merchantUnit,
            'branch_unit_amount' => $branchUnit,
            'merchant_charge' => $merchantCharge,
            'branch_charge' => $branchCharge,
            'line_items' => array_values(array_filter([
                $merchantPlan ? [
                    'description' => $merchantPlan->name,
                    'quantity' => $merchantCount,
                    'unit_amount' => $merchantUnit,
                    'amount' => $merchantCharge,
                ] : null,
                $branchPlan ? [
                    'description' => $branchPlan->name,
                    'quantity' => $branchCount,
                    'unit_amount' => $branchUnit,
                    'amount' => $branchCharge,
                ] : null,
            ])),
            'total' => $total,
            'currency' => 'PHP',
        ];
    }
}
