<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\Billing\VendorLicenseService;
use Illuminate\Http\Request;

class VendorLicenseController extends Controller
{
    public function __construct(
        private readonly VendorLicenseService $vendorLicenseService,
    ) {}

    public function index(Vendor $vendor)
    {
        $this->authorize('billing.viewVendorLicenses', $vendor);

        $licenses = $this->vendorLicenseService->listForVendor($vendor);

        return response()->json([
            'data' => $licenses->map(fn ($license) => $this->transformLicense($license)),
        ]);
    }

    public function store(Request $request, Vendor $vendor)
    {
        $this->authorize('billing.manageVendorLicenses', $vendor);

        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:license_plans,slug'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $license = $this->vendorLicenseService->assign(
            $vendor,
            $data['plan_slug'],
            $data['quantity'] ?? 1,
            $request->user(),
            $data['metadata'] ?? [],
        );

        return response()->json($this->transformLicense($license), 201);
    }

    private function transformLicense($license): array
    {
        return [
            'id' => $license->id,
            'vendor_id' => $license->vendor_id,
            'status' => $license->status->value,
            'quantity' => $license->quantity,
            'purchased_at' => $license->purchased_at?->toIso8601String(),
            'starts_at' => $license->starts_at?->toIso8601String(),
            'ends_at' => $license->ends_at?->toIso8601String(),
            'metadata' => $license->metadata,
            'plan' => [
                'id' => $license->licensePlan->id,
                'name' => $license->licensePlan->name,
                'slug' => $license->licensePlan->slug,
                'billing_model' => $license->licensePlan->billing_model->value,
                'amount' => (float) $license->licensePlan->amount,
                'currency' => $license->licensePlan->currency,
            ],
        ];
    }
}
