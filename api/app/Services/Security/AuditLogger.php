<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $changes = null,
        ?User $user = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => $request?->ip(),
        ]);
    }
}
