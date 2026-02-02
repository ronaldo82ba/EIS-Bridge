<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Vendor;

class ApiKeyMiddleware
{
    public function handle($request, Closure $next)
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing Authorization header.',
            ], 401);
        }

        $apiKey = substr($header, 7);

        $vendor = Vendor::where('api_key', $apiKey)->first();

        if (!$vendor) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid API key.',
            ], 401);
        }

        $request->attributes->set('vendor', $vendor);

        return $next($request);
    }
}
