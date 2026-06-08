<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Models\VendorIpWhitelist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class EnsureVendorIpAllowed
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Vendor|null $vendor */
        $vendor = $request->attributes->get('vendor');

        if (! $vendor) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Vendor context missing.',
            ], 401);
        }

        $entries = VendorIpWhitelist::query()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->pluck('ip_address');

        if ($entries->isEmpty()) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();

        foreach ($entries as $allowed) {
            if (IpUtils::checkIp($clientIp, $allowed)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'forbidden',
            'message' => 'Request IP is not whitelisted for this vendor.',
        ], 403);
    }
}
