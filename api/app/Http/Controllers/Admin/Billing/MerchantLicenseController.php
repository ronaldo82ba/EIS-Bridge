<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Billing\MerchantLicenseService;
use Illuminate\Http\Request;

class MerchantLicenseController extends Controller
{
    public function __construct(
        private readonly MerchantLicenseService $merchantLicenseService,
    ) {}

    public function index(Merchant $merchant)
    {
        $this->authorize('billing.viewMerchantLicenses', $merchant);

        $licenses = $this->merchantLicenseService->listForMerchant($merchant);

        return response()->json([
            'data' => $licenses->map(fn ($license) => $this->transformLicense($license)),
        ]);
    }

    public function store(Request $request, Merchant $merchant)
    {
        $this->authorize('billing.manageMerchantLicenses', $merchant);

        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:license_plans,slug'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $license = $this->merchantLicenseService->assign(
            $merchant,
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
            'merchant_id' => $license->merchant_id,
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
