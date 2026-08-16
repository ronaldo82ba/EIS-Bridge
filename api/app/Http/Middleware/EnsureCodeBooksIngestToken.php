<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine auth for CodeBooks → Bridge ingest (server-to-server).
 * Header: X-Bridge-Ingest-Token (timing-safe compare to CODEBOOKS_INGEST_TOKEN).
 */
class EnsureCodeBooksIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('codebooks.ingest_token');

        if ($expected === '') {
            Log::error('CODEBOOKS_INGEST_TOKEN is not configured.');

            return response()->json([
                'error' => 'ingest_misconfigured',
                'message' => 'CodeBooks ingest token is not configured.',
            ], 503);
        }

        $provided = $request->header('X-Bridge-Ingest-Token');

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('CodeBooks ingest token rejected.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid X-Bridge-Ingest-Token header.',
            ], 401);
        }

        return $next($request);
    }
}
