<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\AuditLogger as SecurityAuditLogger;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $changes = null,
    ): AuditLog {
        return app(SecurityAuditLogger::class)->log(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            changes: $changes,
            user: $user,
            request: Request::instance(),
        );
    }
}
