<?php

namespace App\Http\Controllers\Admin\Logs;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($entityType = $request->string('entity_type')->toString()) {
            $query->where('entity_type', $entityType);
        }

        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $paginated = $query->paginate($request->integer('per_page', 25));

        $paginated->getCollection()->transform(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'user_email' => $log->user?->email,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'changes' => $log->changes,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ];
        });

        return response()->json($paginated);
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user:id,name,email');

        return response()->json([
            'data' => [
                'id' => $auditLog->id,
                'user_id' => $auditLog->user_id,
                'user_name' => $auditLog->user?->name,
                'user_email' => $auditLog->user?->email,
                'action' => $auditLog->action,
                'entity_type' => $auditLog->entity_type,
                'entity_id' => $auditLog->entity_id,
                'changes' => $auditLog->changes,
                'ip_address' => $auditLog->ip_address,
                'created_at' => $auditLog->created_at,
            ],
        ]);
    }
}
