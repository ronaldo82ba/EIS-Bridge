<?php

/**
 * Deviations from user skeleton:
 * - Extends AdminController with policy checks and AdminScope vendor scoping.
 * - index added with branch_id filter for onboarding UI; show retained for admin detail views.
 * - storeForBranch alias route for POST /branches/{branch}/devices.
 * - pos_device_id unique per branch; name defaults to pos_device_id when omitted.
 * - Responses wrapped in { data: ... }; destroy returns 204.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Device;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        $query = AdminScope::scopeDevices(Device::query(), $this->adminUser())
            ->with(['branch:id,name,branch_code,merchant_id'])
            ->orderBy('name');

        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        $device->load('branch.merchant.vendor');

        return response()->json(['data' => $device]);
    }

    public function storeForBranch(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        $request->merge(['branch_id' => $branch->id]);

        return $this->store($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Device::class);

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'pos_device_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices')->where(fn ($query) => $query->where('branch_id', $request->input('branch_id'))),
            ],
            'name'   => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
        ]);

        $branch = Branch::with('merchant')->findOrFail($data['branch_id']);
        if (! AdminScope::belongsToVendor($this->adminUser(), $branch->merchant->vendor_id)) {
            abort(403);
        }

        $device = Device::create([
            ...$data,
            'name'   => $data['name'] ?? $data['pos_device_id'],
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json(['data' => $device], 201);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $data = $request->validate([
            'pos_device_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('devices')
                    ->where(fn ($query) => $query->where('branch_id', $device->branch_id))
                    ->ignore($device->id),
            ],
            'name'   => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'locked'])],
        ]);

        $device->update($data);

        return response()->json(['data' => $device->fresh()]);
    }

    public function destroy(Device $device): JsonResponse
    {
        $this->authorize('delete', $device);

        $device->delete();

        return response()->json(null, 204);
    }
}
