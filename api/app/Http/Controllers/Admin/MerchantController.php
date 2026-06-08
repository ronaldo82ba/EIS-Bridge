<?php

/**
 * Deviations from user skeleton:
 * - Extends AdminController (policies + AuthorizesRequests).
 * - Vendor scoping via AdminScope; vendor_admin cannot create for other vendors.
 * - Uses merchant_code (not in skeleton); auto-generated when omitted.
 * - Responses wrapped in { data: ... } for React admin SPA.
 * - index supports search + configurable per_page (default 15).
 */

namespace App\Http\Controllers\Admin;

use App\Models\CertificateAlert;
use App\Models\Merchant;
use App\Services\Activity\MerchantActivityService;
use App\Services\Analytics\MerchantAnalyticsService;
use App\Services\Analytics\MerchantHealthScoreService;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MerchantController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Merchant::class);

        $query = AdminScope::scopeMerchants(Merchant::query(), $this->adminUser())
            ->with('vendor:id,name')
            ->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('merchant_code', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Merchant $merchant): JsonResponse
    {
        $this->authorize('view', $merchant);

        $merchant->load([
            'vendor:id,name',
            'branches.devices',
            'certificate',
            'ptt',
        ])->append('stats');

        if ($merchant->certificate) {
            $merchant->certificate->setAttribute(
                'expiry_alert',
                CertificateAlert::latestLevelFor($merchant->certificate->id)
            );
        }

        return response()->json(['data' => $merchant]);
    }

    public function analytics(Request $request, Merchant $merchant, MerchantAnalyticsService $analytics): JsonResponse
    {
        $this->authorize('view', $merchant);

        $range = $request->query('range', '7d');
        if (! in_array($range, ['7d', '30d', '90d'], true)) {
            $range = '7d';
        }

        return response()->json([
            'data' => $analytics->getAnalytics($merchant, $range),
        ]);
    }

    public function health(Request $request, Merchant $merchant, MerchantHealthScoreService $healthScore): JsonResponse
    {
        $this->authorize('view', $merchant);

        $range = $request->query('range', '30d');
        if (! in_array($range, ['7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        return response()->json([
            'data' => $healthScore->getHealthScore($merchant, $range),
        ]);
    }

    public function activity(Request $request, Merchant $merchant, MerchantActivityService $activityService): JsonResponse
    {
        $this->authorize('view', $merchant);

        $params = $request->validate([
            'type' => ['nullable', 'string', 'in:all,transaction,mapping,signing,transmission,retry,webhook,certificate'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $paginator = $activityService->paginate($merchant, $params);

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Merchant::class);

        $data = $request->validate([
            'vendor_id'     => ['required', 'exists:vendors,id'],
            'merchant_code' => ['nullable', 'string', 'max:255'],
            'name'          => ['required', 'string', 'max:255'],
            'tin'           => ['required', 'string', 'max:255'],
            'address'       => ['required', 'string'],
            'status'        => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (! AdminScope::belongsToVendor($this->adminUser(), (int) $data['vendor_id'])) {
            abort(403);
        }

        if (empty($data['merchant_code'])) {
            $data['merchant_code'] = $this->generateMerchantCode($data['name'], (int) $data['vendor_id']);
        }

        $data['status'] = $data['status'] ?? 'active';

        $merchant = Merchant::create($data);

        return response()->json(['data' => $merchant], 201);
    }

    public function update(Request $request, Merchant $merchant): JsonResponse
    {
        $this->authorize('update', $merchant);

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'merchant_code' => ['sometimes', 'string', 'max:255'],
            'tin'           => ['sometimes', 'string', 'max:255'],
            'address'       => ['sometimes', 'string'],
            'status'        => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $merchant->update($data);

        return response()->json(['data' => $merchant->fresh()]);
    }

    public function destroy(Merchant $merchant): JsonResponse
    {
        $this->authorize('delete', $merchant);

        $merchant->delete();

        return response()->json(null, 204);
    }

    private function generateMerchantCode(string $name, int $vendorId): string
    {
        $base = Str::upper(Str::slug($name, ''));
        $base = Str::limit($base, 12, '') ?: 'MERCHANT';
        $code = $base;
        $suffix = 1;

        while (Merchant::where('vendor_id', $vendorId)->where('merchant_code', $code)->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }
}
