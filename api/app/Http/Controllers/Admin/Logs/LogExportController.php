<?php

namespace App\Http\Controllers\Admin\Logs;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemLog;
use App\Models\TransmissionLog;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $type = $request->string('type', 'system')->toString();
        $filename = "logs-{$type}-".now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request, $type) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'audit' => $this->exportAudit($handle, $request),
                'transmission' => $this->exportTransmission($handle, $request),
                'webhooks' => $this->exportWebhooks($handle, $request),
                default => $this->exportSystem($handle, $request),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function exportSystem($handle, Request $request): void
    {
        fputcsv($handle, ['id', 'level', 'message', 'channel', 'logged_at']);

        if (! Schema::hasTable('system_logs')) {
            return;
        }

        $query = SystemLog::query()->orderByDesc('logged_at');
        if ($level = $request->string('level')->toString()) {
            $query->where('level', strtolower($level));
        }

        $query->limit(5000)->each(function (SystemLog $log) use ($handle) {
            fputcsv($handle, [
                $log->id,
                $log->level,
                $log->message,
                $log->channel,
                $log->logged_at,
            ]);
        });
    }

    private function exportAudit($handle, Request $request): void
    {
        fputcsv($handle, ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'created_at']);

        AuditLog::query()
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('entity_type')->toString(), fn ($q, $v) => $q->where('entity_type', $v))
            ->orderByDesc('created_at')
            ->limit(5000)
            ->each(function (AuditLog $log) use ($handle) {
                fputcsv($handle, [
                    $log->id,
                    $log->user_id,
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->created_at,
                ]);
            });
    }

    private function exportTransmission($handle, Request $request): void
    {
        fputcsv($handle, ['id', 'invoice_id', 'event', 'timestamp']);

        TransmissionLog::query()
            ->when($request->integer('invoice_id'), fn ($q, $id) => $q->where('invoice_id', $id))
            ->orderByDesc('timestamp')
            ->limit(5000)
            ->each(function (TransmissionLog $log) use ($handle) {
                fputcsv($handle, [
                    $log->id,
                    $log->invoice_id,
                    $log->event,
                    $log->timestamp,
                ]);
            });
    }

    private function exportWebhooks($handle, Request $request): void
    {
        fputcsv($handle, ['id', 'vendor_id', 'invoice_id', 'event', 'status_code', 'success', 'created_at']);

        WebhookDelivery::query()
            ->when($request->integer('vendor_id'), fn ($q, $id) => $q->where('vendor_id', $id))
            ->orderByDesc('created_at')
            ->limit(5000)
            ->each(function (WebhookDelivery $log) use ($handle) {
                fputcsv($handle, [
                    $log->id,
                    $log->vendor_id,
                    $log->invoice_id,
                    $log->event,
                    $log->status_code,
                    $log->success ? '1' : '0',
                    $log->created_at,
                ]);
            });
    }
}
