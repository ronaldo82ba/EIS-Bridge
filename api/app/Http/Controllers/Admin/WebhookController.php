<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Audit\AuditLogger;
use App\Support\AdminScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends AdminController
{
    public function index(Request $request)
    {
        $user = $this->adminUser();

        $vendors = AdminScope::scopeVendors(Vendor::query(), $user)
            ->select(['id', 'name', 'webhook_url', 'status'])
            ->withCount('merchants')
            ->orderBy('name')
            ->get();

        $deliveriesQuery = WebhookDelivery::query()
            ->with('vendor:id,name')
            ->orderByDesc('created_at');

        if ($vendorId = AdminScope::vendorId($user)) {
            $deliveriesQuery->where('vendor_id', $vendorId);
        }

        return response()->json([
            'vendors'    => $vendors,
            'deliveries' => $deliveriesQuery->limit(50)->get(),
        ]);
    }

    public function show(Vendor $vendor)
    {
        if (! AdminScope::belongsToVendor($this->adminUser(), $vendor->id)) {
            abort(403);
        }

        $vendor->makeHidden(['webhook_secret', 'api_key']);

        $deliveries = WebhookDelivery::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'vendor'     => $vendor,
            'deliveries' => $deliveries,
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        if (! AdminScope::belongsToVendor($this->adminUser(), $vendor->id)) {
            abort(403);
        }

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'webhook_secret' => ['nullable', 'string', 'min:8'],
        ]);

        if (array_key_exists('webhook_secret', $data) && $data['webhook_secret'] === null) {
            unset($data['webhook_secret']);
        }

        if (isset($data['webhook_url']) && $data['webhook_url'] && empty($vendor->webhook_secret) && ! isset($data['webhook_secret'])) {
            $data['webhook_secret'] = Str::random(32);
        }

        $old = $vendor->only(['webhook_url']);
        $vendor->update($data);

        AuditLogger::log($this->adminUser(), 'updated_webhook', 'vendor', $vendor->id, [
            'old' => $old,
            'new' => $vendor->only(['webhook_url']),
        ]);

        $vendor->makeHidden(['webhook_secret', 'api_key']);

        return response()->json(['data' => $vendor]);
    }

    public function test(Vendor $vendor)
    {
        if (! AdminScope::belongsToVendor($this->adminUser(), $vendor->id)) {
            abort(403);
        }

        if (! $vendor->webhook_url) {
            return response()->json([
                'message' => 'Configure a webhook URL before testing.',
            ], 422);
        }

        return response()->json([
            'status' => 'pending',
            'message' => 'Webhook test is not wired yet; delivery logging is available.',
        ]);
    }

    public function deliveries(Vendor $vendor, Request $request)
    {
        if (! AdminScope::belongsToVendor($this->adminUser(), $vendor->id)) {
            abort(403);
        }

        $deliveries = WebhookDelivery::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($deliveries);
    }
}
