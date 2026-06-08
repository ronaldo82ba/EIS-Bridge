<?php

namespace App\Services\Billing;

use App\Enums\LicenseStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLicense;
use Illuminate\Support\Collection;

class VendorLicenseService
{
    public function __construct(
        private readonly LicensePlanCatalog $catalog,
    ) {}

    public function listForVendor(Vendor $vendor): Collection
    {
        return $vendor->licenses()
            ->with('licensePlan')
            ->orderByDesc('created_at')
            ->get();
    }

    public function assign(
        Vendor $vendor,
        string $planSlug,
        int $quantity = 1,
        ?User $performer = null,
        array $metadata = [],
    ): VendorLicense {
        $plan = $this->catalog->requireBySlug($planSlug);

        $license = $vendor->licenses()->create([
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Active->value,
            'purchased_at' => now(),
            'starts_at' => now(),
            'quantity' => max(1, $quantity),
            'metadata' => $metadata ?: null,
        ]);

        BillingEventLogger::log('activated', $license, $plan, $performer, [
            'vendor_id' => $vendor->id,
            'quantity' => $license->quantity,
        ]);

        return $license->load('licensePlan');
    }

    public function activate(VendorLicense $license, ?User $performer = null): VendorLicense
    {
        $license->update(['status' => LicenseStatus::Active->value]);

        BillingEventLogger::log('activated', $license, $license->licensePlan, $performer);

        return $license->fresh('licensePlan');
    }

    public function suspend(VendorLicense $license, ?User $performer = null): VendorLicense
    {
        $license->update(['status' => LicenseStatus::Suspended->value]);

        BillingEventLogger::log('suspended', $license, $license->licensePlan, $performer);

        return $license->fresh('licensePlan');
    }

    public function calculateMonthlyHosting(Vendor $vendor): array
    {
        $merchantCount = $vendor->merchants()->count();
        $lineItems = [];
        $total = 0.0;

        $activeLicenses = $vendor->licenses()
            ->active()
            ->with('licensePlan')
            ->get();

        foreach ($activeLicenses as $license) {
            $plan = $license->licensePlan;
            $slug = $plan->slug;

            if ($slug === 'vendor_monthly_hosting') {
                $amount = (float) $plan->amount;
                $lineItems[] = $this->lineItem($plan->name, 1, (float) $plan->amount, $amount);
                $total += $amount;
            }

            if ($slug === 'vendor_per_merchant') {
                $units = max($merchantCount, $license->quantity);
                $amount = round($units * (float) $plan->amount, 2);
                $lineItems[] = $this->lineItem($plan->name, $units, (float) $plan->amount, $amount);
                $total += $amount;
            }
        }

        return [
            'merchant_count' => $merchantCount,
            'line_items' => $lineItems,
            'total' => round($total, 2),
            'currency' => 'PHP',
        ];
    }

    private function lineItem(string $description, int $quantity, float $unitAmount, float $amount): array
    {
        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'amount' => $amount,
        ];
    }
}
