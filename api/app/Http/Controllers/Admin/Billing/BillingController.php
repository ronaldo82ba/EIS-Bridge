<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Merchant;
use App\Models\MerchantLicense;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLicense;
use App\Services\Billing\BillingInvoiceGenerator;
use App\Services\Billing\SaasBillingService;
use App\Services\Billing\VendorLicenseService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly SaasBillingService $saasBillingService,
        private readonly VendorLicenseService $vendorLicenseService,
        private readonly BillingInvoiceGenerator $invoiceGenerator,
    ) {}

    public function summary(Request $request)
    {
        $this->authorize('billing.viewSummary');

        $user = $request->user();
        $vendor = $this->resolveScopedVendor($user);

        $saas = $this->saasBillingService->calculateMonthlyCharges($vendor);
        $vendorHosting = $vendor
            ? $this->vendorLicenseService->calculateMonthlyHosting($vendor)
            : ['total' => 0];

        $vendorLicenseQuery = VendorLicense::query()->active();
        $merchantLicenseQuery = MerchantLicense::query()->active();
        $invoiceQuery = BillingInvoice::query();

        if ($vendor) {
            $vendorLicenseQuery->where('vendor_id', $vendor->id);
            $merchantLicenseQuery->whereHas('merchant', fn ($query) => $query->where('vendor_id', $vendor->id));
            $invoiceQuery->where(function ($query) use ($vendor) {
                $query->where(function ($inner) use ($vendor) {
                    $inner->where('billable_type', Vendor::class)
                        ->where('billable_id', $vendor->id);
                })->orWhereHasMorph('billable', [Merchant::class], function ($inner) use ($vendor) {
                    $inner->where('vendor_id', $vendor->id);
                });
            });
        }

        $mrr = round($saas['total'] + ($vendorHosting['total'] ?? 0), 2);

        return response()->json([
            'mrr' => $mrr,
            'currency' => 'PHP',
            'active_vendor_licenses' => $vendorLicenseQuery->count(),
            'active_merchant_licenses' => $merchantLicenseQuery->count(),
            'overdue_invoices' => (clone $invoiceQuery)->overdue()->count(),
            'saas' => $saas,
            'vendor_hosting' => $vendorHosting,
        ]);
    }

    public function invoices(Request $request)
    {
        $this->authorize('billing.viewInvoices');

        $user = $request->user();
        $vendor = $this->resolveScopedVendor($user);

        $query = BillingInvoice::query()->with('billable')->orderByDesc('created_at');

        if ($vendor) {
            $query->where(function ($inner) use ($vendor) {
                $inner->where(function ($q) use ($vendor) {
                    $q->where('billable_type', Vendor::class)
                        ->where('billable_id', $vendor->id);
                })->orWhereHasMorph('billable', [Merchant::class], function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id);
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $invoices = $query->paginate($request->integer('per_page', 25));
        $invoices->getCollection()->transform(fn (BillingInvoice $invoice) => $this->transformInvoice($invoice));

        return response()->json($invoices);
    }

    public function show(BillingInvoice $invoice)
    {
        $this->authorize('billing.viewInvoice', $invoice);

        $invoice->load('billable');

        return response()->json($this->transformInvoice($invoice, true));
    }

    public function generate(Request $request)
    {
        $this->authorize('billing.generateInvoices');

        $data = $request->validate([
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
        ]);

        $periodStart = isset($data['period_start']) ? now()->parse($data['period_start'])->startOfDay() : now()->startOfMonth();
        $periodEnd = isset($data['period_end']) ? now()->parse($data['period_end'])->endOfDay() : now()->endOfMonth();

        $invoices = $this->invoiceGenerator->generateMonthlyInvoices(
            $periodStart,
            $periodEnd,
            $request->user(),
        );

        return response()->json([
            'generated_count' => $invoices->count(),
            'invoices' => $invoices->map(fn (BillingInvoice $invoice) => $this->transformInvoice($invoice)),
        ], 201);
    }

    private function resolveScopedVendor(User $user): ?Vendor
    {
        if ($user->role === 'vendor_admin' && $user->vendor_id) {
            return Vendor::find($user->vendor_id);
        }

        return null;
    }

    private function transformInvoice(BillingInvoice $invoice, bool $detailed = false): array
    {
        $payload = [
            'id' => $invoice->id,
            'billable_type' => class_basename($invoice->billable_type),
            'billable_id' => $invoice->billable_id,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status->value,
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['line_items'] = $invoice->line_items;
            $payload['billable'] = $invoice->billable ? [
                'id' => $invoice->billable->id,
                'name' => $invoice->billable->name ?? null,
            ] : null;
        }

        return $payload;
    }
}
