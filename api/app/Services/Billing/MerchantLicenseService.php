<?php

namespace App\Services\Billing;

use App\Enums\LicenseStatus;
use App\Models\Merchant;
use App\Models\MerchantLicense;
use App\Models\User;
use Illuminate\Support\Collection;

class MerchantLicenseService
{
    public function __construct(
        private readonly LicensePlanCatalog $catalog,
    ) {}

    public function listForMerchant(Merchant $merchant): Collection
    {
        return $merchant->licenses()
            ->with('licensePlan')
            ->orderByDesc('created_at')
            ->get();
    }

    public function assign(
        Merchant $merchant,
        string $planSlug,
        int $quantity = 1,
        ?User $performer = null,
        array $metadata = [],
    ): MerchantLicense {
        $plan = $this->catalog->requireBySlug($planSlug);

        $license = $merchant->licenses()->create([
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Active->value,
            'purchased_at' => now(),
            'starts_at' => now(),
            'quantity' => max(1, $quantity),
            'metadata' => $metadata ?: null,
        ]);

        BillingEventLogger::log('activated', $license, $plan, $performer, [
            'merchant_id' => $merchant->id,
            'quantity' => $license->quantity,
        ]);

        return $license->load('licensePlan');
    }

    public function activate(MerchantLicense $license, ?User $performer = null): MerchantLicense
    {
        $license->update(['status' => LicenseStatus::Active->value]);

        BillingEventLogger::log('activated', $license, $license->licensePlan, $performer);

        return $license->fresh('licensePlan');
    }

    public function suspend(MerchantLicense $license, ?User $performer = null): MerchantLicense
    {
        $license->update(['status' => LicenseStatus::Suspended->value]);

        BillingEventLogger::log('suspended', $license, $license->licensePlan, $performer);

        return $license->fresh('licensePlan');
    }

    public function calculateMonthlyBranchFees(Merchant $merchant): array
    {
        $branchCount = $merchant->branches()->count();
        $lineItems = [];
        $total = 0.0;

        $activeLicenses = $merchant->licenses()
            ->active()
            ->with('licensePlan')
            ->get();

        foreach ($activeLicenses as $license) {
            $plan = $license->licensePlan;

            if ($plan->slug !== 'merchant_per_branch_monthly') {
                continue;
            }

            $units = max($branchCount, $license->quantity);
            $amount = round($units * (float) $plan->amount, 2);
            $lineItems[] = [
                'description' => $plan->name,
                'quantity' => $units,
                'unit_amount' => (float) $plan->amount,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [
            'branch_count' => $branchCount,
            'line_items' => $lineItems,
            'total' => round($total, 2),
            'currency' => 'PHP',
        ];
    }
}
