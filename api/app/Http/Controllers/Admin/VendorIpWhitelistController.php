<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorIpWhitelist;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;

class VendorIpWhitelistController extends Controller
{
    public function index(Vendor $vendor)
    {
        $this->authorize('manageIpWhitelist', $vendor);

        $entries = $vendor->ipWhitelists()
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request, Vendor $vendor, AuditLogger $auditLogger)
    {
        $this->authorize('manageIpWhitelist', $vendor);

        $data = $request->validate([
            'ip_address' => ['required', 'string', 'max:45'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $entry = $vendor->ipWhitelists()->create([
            'ip_address' => $data['ip_address'],
            'label' => $data['label'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        $auditLogger->log(
            action: 'vendor.ip_whitelist.created',
            entityType: 'vendor_ip_whitelist',
            entityId: $entry->id,
            changes: $data,
            user: $request->user(),
            request: $request,
        );

        return response()->json(['data' => $entry], 201);
    }

    public function destroy(Request $request, Vendor $vendor, VendorIpWhitelist $whitelist, AuditLogger $auditLogger)
    {
        $this->authorize('manageIpWhitelist', $vendor);

        if ($whitelist->vendor_id !== $vendor->id) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Whitelist entry not found for this vendor.',
            ], 404);
        }

        $whitelist->delete();

        $auditLogger->log(
            action: 'vendor.ip_whitelist.deleted',
            entityType: 'vendor_ip_whitelist',
            entityId: $whitelist->id,
            user: $request->user(),
            request: $request,
        );

        return response()->json(['message' => 'Whitelist entry removed.']);
    }
}
