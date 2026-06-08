<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\LicensePlan;
use App\Services\Billing\LicensePlanCatalog;
use Illuminate\Http\Request;

class LicensePlanController extends Controller
{
    public function __construct(
        private readonly LicensePlanCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('billing.viewPlans');

        $category = $request->string('category')->toString() ?: null;
        $plans = $this->catalog->byCategory($category);

        return response()->json([
            'data' => $plans->map(fn (LicensePlan $plan) => $this->transformPlan($plan)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('billing.managePlans');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:license_plans,slug'],
            'billing_model' => ['required', 'in:one_time,per_unit,recurring_monthly'],
            'unit' => ['nullable', 'in:merchant,branch,vendor'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = LicensePlan::create([
            ...$data,
            'currency' => $data['currency'] ?? 'PHP',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($this->transformPlan($plan), 201);
    }

    private function transformPlan(LicensePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'billing_model' => $plan->billing_model->value,
            'unit' => $plan->unit?->value,
            'amount' => (float) $plan->amount,
            'currency' => $plan->currency,
            'is_active' => $plan->is_active,
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }
}
