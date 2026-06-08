<?php

namespace App\Http\Middleware;

use App\Enums\SupportWriteAction;
use Closure;
use Illuminate\Http\Request;

class EnsureSupportWriteAction
{
    public function handle(Request $request, Closure $next, string $action)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'support') {
            return $next($request);
        }

        $allowed = SupportWriteAction::tryFrom($action);

        if (! $allowed) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Unsupported support action.',
            ], 403);
        }

        $request->attributes->set('support_write_action', $allowed->value);

        return $next($request);
    }
}
