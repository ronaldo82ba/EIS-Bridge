<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BillingInvoiceGenerator
{
    public function __construct(
        private readonly VendorLicenseService $vendorLicenseService,
        private readonly MerchantLicenseService $merchantLicenseService,
        private readonly SaasBillingService $saasBillingService,
    ) {}

    public function generateMonthlyInvoices(
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        ?User $performer = null,
    ): Collection {
        $periodStart ??= now()->startOfMonth();
        $periodEnd ??= now()->endOfMonth();

        $invoices = collect();

        Vendor::query()->orderBy('id')->each(function (Vendor $vendor) use ($periodStart, $periodEnd, $performer, $invoices) {
            $lineItems = [];
            $total = 0.0;

            $vendorCharges = $this->vendorLicenseService->calculateMonthlyHosting($vendor);
            $lineItems = array_merge($lineItems, $vendorCharges['line_items']);
            $total += $vendorCharges['total'];

            $saasCharges = $this->saasBillingService->calculateMonthlyCharges($vendor);
            $lineItems = array_merge($lineItems, $saasCharges['line_items']);
            $total += $saasCharges['total'];

            if ($total <= 0) {
                return;
            }

            $invoice = BillingInvoice::create([
                'billable_type' => $vendor->getMorphClass(),
                'billable_id' => $vendor->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'amount' => round($total, 2),
                'currency' => 'PHP',
                'status' => BillingInvoiceStatus::Issued->value,
                'due_at' => $periodEnd->copy()->addDays(15),
                'line_items' => $lineItems,
            ]);

            BillingEventLogger::log('invoice_issued', $invoice, null, $performer, [
                'vendor_id' => $vendor->id,
                'amount' => $invoice->amount,
            ]);

            $invoices->push($invoice);
        });

        Merchant::query()->with('licenses.licensePlan')->orderBy('id')->each(function (Merchant $merchant) use ($periodStart, $periodEnd, $performer, $invoices) {
            $branchFees = $this->merchantLicenseService->calculateMonthlyBranchFees($merchant);

            if ($branchFees['total'] <= 0) {
                return;
            }

            $invoice = BillingInvoice::create([
                'billable_type' => $merchant->getMorphClass(),
                'billable_id' => $merchant->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'amount' => $branchFees['total'],
                'currency' => 'PHP',
                'status' => BillingInvoiceStatus::Issued->value,
                'due_at' => $periodEnd->copy()->addDays(15),
                'line_items' => $branchFees['line_items'],
            ]);

            BillingEventLogger::log('invoice_issued', $invoice, null, $performer, [
                'merchant_id' => $merchant->id,
                'amount' => $invoice->amount,
            ]);

            $invoices->push($invoice);
        });

        return $invoices;
    }
}
