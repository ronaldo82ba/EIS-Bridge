<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSandboxApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('eis.sandbox_mode')) {
            return $next($request);
        }

        $expected = (string) config('security.sandbox_api_key');

        if ($expected === '') {
            Log::error('SANDBOX_API_KEY is not configured while EIS_SANDBOX_MODE=true.');

            return response()->json([
                'error' => 'sandbox_misconfigured',
                'message' => 'Sandbox API key is not configured.',
            ], 503);
        }

        $provided = $request->header('X-SANDBOX-API-KEY');

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Sandbox API key rejected.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid X-SANDBOX-API-KEY header.',
            ], 401);
        }

        return $next($request);
    }
}
