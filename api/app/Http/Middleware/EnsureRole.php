<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], 401);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->all();

        if (! in_array($user->role, $allowedRoles, true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}
