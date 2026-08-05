<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use App\Services\CodeBooks\CodeBooksIngestAdapter;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeBooksIngestController extends AdminController
{
    public function ingest(Request $request, CodeBooksIngestAdapter $adapter): JsonResponse
    {
        $user = $this->adminUser();

        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            // Default dry_run=true for safety; commit only when explicitly false.
            'dry_run' => ['sometimes', 'boolean'],
            'payload' => ['required', 'array'],
            'payload.company' => ['required', 'array'],
            'payload.documents' => ['required', 'array', 'min:1'],
            'payload.meta' => ['nullable', 'array'],
        ]);

        $dryRun = array_key_exists('dry_run', $data) ? (bool) $data['dry_run'] : true;

        if ($user->isVendorAdmin()) {
            $vendorId = (int) $user->vendor_id;
        } else {
            $vendorId = (int) ($data['vendor_id'] ?? 0);
            if ($vendorId < 1) {
                return response()->json([
                    'message' => 'vendor_id is required for this role.',
                ], 422);
            }
        }

        if (! AdminScope::belongsToVendor($user, $vendorId)) {
            abort(403);
        }

        $vendor = Vendor::query()->findOrFail($vendorId);
        $result = $adapter->ingest($data['payload'], $vendor, $dryRun);

        return response()->json([
            'data' => $result,
        ], $dryRun ? 200 : 201);
    }
}
