<?php

/**
 * Deviations from user skeleton:
 * - Extends AdminController with policy checks and AdminScope vendor scoping.
 * - Column is branch_code; skeleton field `code` is accepted and mapped to branch_code.
 * - index added with merchant_id filter for onboarding UI.
 * - Responses wrapped in { data: ... }; destroy returns 204.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Merchant;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $query = AdminScope::scopeBranches(Branch::query(), $this->adminUser())
            ->with(['merchant:id,name,merchant_code,vendor_id'])
            ->orderBy('name');

        if ($merchantId = $request->query('merchant_id')) {
            $query->where('merchant_id', $merchantId);
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        $branch->load(['merchant.vendor', 'devices']);

        return response()->json(['data' => $branch]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $this->normalizeBranchCode($request);

        $data = $request->validate([
            'merchant_id' => ['required', 'exists:merchants,id'],
            'branch_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches')->where(fn ($query) => $query->where('merchant_id', $request->input('merchant_id'))),
            ],
            'name'    => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status'  => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $merchant = Merchant::findOrFail($data['merchant_id']);
        if (! AdminScope::belongsToVendor($this->adminUser(), $merchant->vendor_id)) {
            abort(403);
        }

        $branch = Branch::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json(['data' => $branch], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $this->normalizeBranchCode($request);

        $data = $request->validate([
            'branch_code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('branches')
                    ->where(fn ($query) => $query->where('merchant_id', $branch->merchant_id))
                    ->ignore($branch->id),
            ],
            'name'    => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status'  => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $branch->update($data);

        return response()->json(['data' => $branch->fresh()]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json(null, 204);
    }

    /** Map skeleton field `code` to persisted branch_code. */
    private function normalizeBranchCode(Request $request): void
    {
        if ($request->filled('code') && ! $request->filled('branch_code')) {
            $request->merge(['branch_code' => $request->input('code')]);
        }
    }
}
