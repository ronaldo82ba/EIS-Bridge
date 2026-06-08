<?php

namespace App\Http\Controllers\Admin;

use App\Models\Alert;
use App\Models\CertificateAlert;
use App\Models\Invoice;
use App\Models\TransmissionLog;
use App\Support\AdminScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends AdminController
{
    public function index(Request $request)
    {
        $user = $this->adminUser();
        $today = now()->startOfDay();

        $invoiceQuery = AdminScope::scopeInvoices(Invoice::query(), $user);
        $todayQuery = (clone $invoiceQuery)->where('created_at', '>=', $today);

        $totalToday = (clone $todayQuery)->count();

        $sent = (clone $todayQuery)->where(function ($q) {
            $q->where('processing_status', 'sent')
                ->orWhere('eis_status', 'sent');
        })->count();

        $acknowledged = (clone $todayQuery)->where('eis_status', 'acknowledged')->count();

        $rejected = (clone $todayQuery)->where(function ($q) {
            $q->where('processing_status', 'rejected')
                ->orWhere('eis_status', 'rejected')
                ->orWhere('processing_status', 'failed');
        })->count();

        $queueDepth = 0;
        $failedCount = 0;

        if (DB::getSchemaBuilder()->hasTable('jobs')) {
            $queueDepth = DB::table('jobs')->whereNull('reserved_at')->count();
        }

        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failedCount = DB::table('failed_jobs')->count();
        }

        $recentErrors = $this->latestErrors($user);
        $alerts = $this->alertSummary();
        $certificateAlerts = $this->certificateAlertSummary($user);

        return response()->json([
            'invoice_stats' => [
                'total_today'  => $totalToday,
                'sent'         => $sent,
                'acknowledged' => $acknowledged,
                'rejected'     => $rejected,
            ],
            'kpis' => [
                'total_invoices_today' => $totalToday,
                'sent'                 => $sent,
                'acknowledged'         => $acknowledged,
                'rejected'             => $rejected,
                'queue_depth'          => $queueDepth,
            ],
            'queue' => [
                'pending_count' => $queueDepth,
                'failed_count'  => $failedCount,
            ],
            'recent_errors' => $recentErrors,
            'latest_errors' => $recentErrors,
            'alerts'        => $alerts,
            'certificate_alerts' => $certificateAlerts,
        ]);
    }

    private function alertSummary(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('alerts')) {
            return [
                'critical'       => 0,
                'warning'        => 0,
                'unacknowledged' => 0,
            ];
        }

        $active = Alert::query()->whereNull('resolved_at');

        return [
            'critical'       => (clone $active)->where('severity', Alert::SEVERITY_CRITICAL)->count(),
            'warning'        => (clone $active)->where('severity', Alert::SEVERITY_WARNING)->count(),
            'unacknowledged' => (clone $active)->whereNull('acknowledged_at')->count(),
        ];
    }

    private function certificateAlertSummary($user): array
    {
        if (! DB::getSchemaBuilder()->hasTable('certificate_alerts')) {
            return [
                'count'  => 0,
                'recent' => [],
            ];
        }

        $query = CertificateAlert::query()
            ->with([
                'certificate:id,merchant_id,filename,expires_at',
                'certificate.merchant:id,name,merchant_code,vendor_id',
            ])
            ->orderByDesc('created_at');

        if ($vendorId = AdminScope::vendorId($user)) {
            $query->whereHas('certificate.merchant', fn ($q) => $q->where('vendor_id', $vendorId));
        }

        return [
            'count'  => (clone $query)->count(),
            'recent' => (clone $query)->limit(5)->get()->map(fn (CertificateAlert $alert) => [
                'id'             => $alert->id,
                'level'          => $alert->level,
                'notified_admin' => $alert->notified_admin,
                'notified_vendor'=> $alert->notified_vendor,
                'created_at'     => $alert->created_at?->toIso8601String(),
                'certificate'    => $alert->certificate ? [
                    'id'         => $alert->certificate->id,
                    'filename'   => $alert->certificate->filename,
                    'expires_at' => $alert->certificate->expires_at?->toIso8601String(),
                    'merchant'   => $alert->certificate->merchant ? [
                        'id'            => $alert->certificate->merchant->id,
                        'name'          => $alert->certificate->merchant->name,
                        'merchant_code' => $alert->certificate->merchant->merchant_code,
                    ] : null,
                ] : null,
            ])->values()->all(),
        ];
    }

    private function latestErrors($user): array
    {
        $errors = [];

        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(5)
                ->get(['id', 'queue', 'exception', 'failed_at']);

            foreach ($failedJobs as $job) {
                $errors[] = [
                    'id'         => $job->id,
                    'source'     => 'failed_job',
                    'message'    => str($job->exception)->before("\n")->limit(200)->value(),
                    'exception'  => str($job->exception)->before("\n")->limit(200)->value(),
                    'queue'      => $job->queue,
                    'failed_at'  => $job->failed_at,
                    'timestamp'  => $job->failed_at,
                ];
            }
        }

        if (count($errors) < 5) {
            $logQuery = TransmissionLog::query()
                ->with('invoice:id,bridge_transaction_id,merchant_code,processing_status')
                ->where(function ($q) {
                    $q->where('event', 'like', '%error%')
                        ->orWhere('event', 'like', '%fail%')
                        ->orWhere('event', 'like', '%reject%');
                })
                ->orderByDesc('timestamp')
                ->limit(5 - count($errors));

            if (AdminScope::vendorId($user)) {
                $logQuery->whereHas('invoice', function ($q) use ($user) {
                    AdminScope::scopeInvoices($q, $user);
                });
            }

            foreach ($logQuery->get() as $log) {
                $errors[] = [
                    'id'                     => $log->id,
                    'source'                 => 'transmission_log',
                    'message'                => $log->event,
                    'exception'              => $log->event,
                    'bridge_transaction_id'  => $log->invoice?->bridge_transaction_id,
                    'merchant_code'          => $log->invoice?->merchant_code,
                    'processing_status'      => $log->invoice?->processing_status,
                    'invoice_id'             => $log->invoice_id,
                    'failed_at'              => $log->timestamp?->toIso8601String(),
                    'timestamp'              => $log->timestamp?->toIso8601String(),
                ];
            }
        }

        return $errors;
    }
}
