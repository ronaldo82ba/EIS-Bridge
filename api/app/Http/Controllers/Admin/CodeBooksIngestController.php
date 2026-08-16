<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use App\Services\CodeBooks\CodeBooksIngestAdapter;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeBooksIngestController extends AdminController
{
    /**
     * Admin Sanctum path (portal / interactive).
     */
    public function ingest(Request $request, CodeBooksIngestAdapter $adapter): JsonResponse
    {
        $user = $this->adminUser();
        $data = $this->validatedIngestRequest($request);

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

        return $this->runIngest($adapter, $data['payload'], $vendorId, $dryRun);
    }

    /**
     * Machine auth path for CodeBooks server→server (X-Bridge-Ingest-Token).
     * Tenant: CODEBOOKS_INGEST_VENDOR_ID and/or request vendor_id (must agree when both set).
     */
    public function ingestWithToken(Request $request, CodeBooksIngestAdapter $adapter): JsonResponse
    {
        $data = $this->validatedIngestRequest($request);
        $dryRun = array_key_exists('dry_run', $data) ? (bool) $data['dry_run'] : true;

        $configuredVendorId = config('codebooks.ingest_vendor_id');
        $requestVendorId = isset($data['vendor_id']) ? (int) $data['vendor_id'] : null;

        if ($configuredVendorId !== null && $configuredVendorId > 0) {
            if ($requestVendorId !== null && $requestVendorId > 0 && $requestVendorId !== (int) $configuredVendorId) {
                return response()->json([
                    'message' => 'vendor_id does not match configured CODEBOOKS_INGEST_VENDOR_ID.',
                ], 403);
            }
            $vendorId = (int) $configuredVendorId;
        } else {
            $vendorId = (int) ($requestVendorId ?? 0);
            if ($vendorId < 1) {
                return response()->json([
                    'message' => 'vendor_id is required (set CODEBOOKS_INGEST_VENDOR_ID or pass vendor_id).',
                ], 422);
            }
        }

        return $this->runIngest($adapter, $data['payload'], $vendorId, $dryRun);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedIngestRequest(Request $request): array
    {
        return $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            // Default dry_run=true for safety; commit only when explicitly false.
            'dry_run' => ['sometimes', 'boolean'],
            'payload' => ['required', 'array'],
            'payload.company' => ['required', 'array'],
            'payload.documents' => ['required', 'array', 'min:1'],
            'payload.meta' => ['nullable', 'array'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runIngest(
        CodeBooksIngestAdapter $adapter,
        array $payload,
        int $vendorId,
        bool $dryRun,
    ): JsonResponse {
        $vendor = Vendor::query()->findOrFail($vendorId);

        if ($vendor->status === 'suspended') {
            return response()->json([
                'error' => 'vendor_suspended',
                'message' => 'Vendor account is suspended.',
            ], 403);
        }

        $result = $adapter->ingest($payload, $vendor, $dryRun);

        return response()->json([
            'data' => $result,
        ], $dryRun ? 200 : 201);
    }
}
