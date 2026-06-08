<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function revokeAll(Request $request, AuditLogger $auditLogger)
    {
        $user = $request->user();
        $count = $user->tokens()->count();

        $user->tokens()->delete();

        $auditLogger->log(
            action: 'tokens.revoke_all',
            entityType: 'user',
            entityId: $user->id,
            changes: ['revoked_count' => $count],
            user: $user,
            request: $request,
        );

        return response()->json([
            'message' => 'All personal access tokens revoked.',
            'revoked_count' => $count,
        ]);
    }
}
