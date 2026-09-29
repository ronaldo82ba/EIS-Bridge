<?php

namespace App\Http\Middleware;

use App\Services\Billing\LicenseEnforcement;
use App\Services\Security\VendorApiKeyService;
use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function __construct(
        private readonly VendorApiKeyService $apiKeyService,
        private readonly LicenseEnforcement $licenseEnforcement,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing Authorization header.',
            ], 401);
        }

        $apiKey = substr($header, 7);
        $vendor = $this->apiKeyService->validate($apiKey);

        if (! $vendor) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid API key.',
            ], 401);
        }

        if ($vendor->status === 'suspended') {
            return response()->json([
                'error' => 'vendor_suspended',
                'message' => 'Vendor account is suspended.',
            ], 403);
        }

        if (! $this->licenseEnforcement->canVendorOperate($vendor)) {
            return response()->json([
                'error' => 'license_suspended',
                'message' => 'Vendor license is not active.',
            ], 403);
        }

        $request->attributes->set('vendor', $vendor);

        return $next($request);
    }
}
