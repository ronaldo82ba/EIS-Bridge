<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use App\Services\Analytics\VendorAnalyticsService;
use App\Services\Analytics\VendorHealthScoreService;
use Illuminate\Http\JsonResponse;
use App\Services\Security\AuditLogger;
use App\Services\Security\VendorApiKeyService;
use App\Support\AdminScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Vendor::class);

        $query = AdminScope::scopeVendors(Vendor::query(), $this->adminUser())
            ->withCount('merchants')
            ->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $vendors = $query->paginate($request->integer('per_page', 15));

        return response()->json($vendors);
    }

    public function analytics(Request $request, Vendor $vendor, VendorAnalyticsService $analytics)
    {
        $this->authorize('view', $vendor);

        $range = $request->query('range', '30d');
        if (! in_array($range, ['7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        return response()->json([
            'data' => $analytics->getAnalytics($vendor, $range),
        ]);
    }

    public function health(Request $request, Vendor $vendor, VendorHealthScoreService $healthScore): JsonResponse
    {
        $this->authorize('view', $vendor);

        $range = $request->query('range', '30d');
        if (! in_array($range, ['7d', '30d', '90d'], true)) {
            $range = '30d';
        }

        return response()->json([
            'data' => $healthScore->getHealthScore($vendor, $range),
        ]);
    }

    public function show(Vendor $vendor)
    {
        $this->authorize('view', $vendor);

        $vendor->load([
            'merchants',
            'webhookDeliveries' => fn ($query) => $query->latest()->limit(20),
            'ipWhitelists' => fn ($query) => $query->where('is_active', true)->orderBy('ip_address'),
        ])->append('stats');

        $vendor->makeHidden(['webhook_secret']);

        return response()->json(['data' => $vendor]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Vendor::class);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'status'      => ['nullable', 'in:active,suspended'],
        ]);

        $vendor = Vendor::create([
            ...$data,
            'api_key'        => '',
            'webhook_secret' => Str::random(32),
            'status'         => $data['status'] ?? 'active',
        ]);

        $result = app(VendorApiKeyService::class)->assignInitialKey($vendor);

        app(AuditLogger::class)->log(
            action: 'vendor.created',
            entityType: 'vendor',
            entityId: $vendor->id,
            changes: ['name' => $vendor->name],
            user: $this->adminUser(),
            request: $request,
        );

        return response()->json([
            'data' => $result['vendor'],
            'api_key' => $result['plain_key'],
        ], 201);
    }

    public function rotateApiKey(
        Request $request,
        Vendor $vendor,
        VendorApiKeyService $apiKeyService,
        AuditLogger $auditLogger,
    ) {
        $this->authorize('rotateApiKey', $vendor);

        $result = $apiKeyService->rotate($vendor);

        $auditLogger->log(
            action: 'vendor.api_key_rotated',
            entityType: 'vendor',
            entityId: $vendor->id,
            changes: [
                'rotated_at' => $result['vendor']->api_key_rotated_at?->toIso8601String(),
            ],
            user: $this->adminUser(),
            request: $request,
        );

        return response()->json([
            'message' => 'API key rotated. Store the new key securely — it will not be shown again.',
            'api_key' => $result['plain_key'],
            'rotated_at' => $result['vendor']->api_key_rotated_at?->toIso8601String(),
            'grace_hours' => (int) config('security.api_key_grace_hours', 24),
        ]);
    }

    public function suspend(Request $request, Vendor $vendor, AuditLogger $auditLogger)
    {
        $this->authorize('update', $vendor);

        $vendor->update(['status' => 'suspended']);

        $auditLogger->log(
            action: 'vendor.suspended',
            entityType: 'vendor',
            entityId: $vendor->id,
            user: $this->adminUser(),
            request: $request,
        );

        return response()->json([
            'status' => 'success',
            'data' => $vendor->fresh(),
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'webhook_url'    => ['nullable', 'url', 'max:2048'],
            'webhook_secret' => ['nullable', 'string', 'min:8'],
            'status'         => ['sometimes', 'in:active,suspended'],
        ]);

        if (array_key_exists('webhook_secret', $data) && ($data['webhook_secret'] === null || $data['webhook_secret'] === '')) {
            unset($data['webhook_secret']);
        }

        $vendor->update($data);

        $vendor = $vendor->fresh()->append('stats');
        $vendor->makeHidden(['webhook_secret']);

        return response()->json(['data' => $vendor]);
    }

    public function destroy(Vendor $vendor)
    {
        $this->authorize('delete', $vendor);

        $vendor->delete();

        return response()->json(null, 204);
    }
}
