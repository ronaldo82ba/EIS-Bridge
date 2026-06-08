<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], 401);
        }

        if (! $user->hasAdminAccess()) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
