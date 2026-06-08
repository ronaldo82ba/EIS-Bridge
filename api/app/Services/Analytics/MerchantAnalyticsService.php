<?php

namespace App\Services\Analytics;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\TransmissionLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MerchantAnalyticsService
{
    private const ERROR_EVENTS = [
        'mapping_failed',
        'signing_failed',
        'transmission_failed',
        'eis_rejected',
        'retry_transmission_failed',
    ];

    private const FAILED_STATUSES = [
        'failed',
        'retry_failed',
        'transmission_failed',
    ];

    public function getAnalytics(Merchant $merchant, string $range = '7d'): array
    {
        $days = $this->resolveDays($range);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $baseQuery = $this->invoiceQuery($merchant)
            ->whereBetween('invoices.created_at', [$from, $to]);

        $kpi = $this->buildKpi(clone $baseQuery);
        $daily = $this->buildDailySeries(clone $baseQuery, $from, $to);
        $eisBreakdown = $this->buildEisBreakdown(clone $baseQuery);
        $branchVolume = $this->buildBranchVolume(clone $baseQuery, $merchant);
        $deviceVolume = $this->buildDeviceVolume(clone $baseQuery);
        $errors = $this->buildErrorBreakdown($merchant, $from, $to);
        $certificateHealth = $this->buildCertificateHealth($merchant);
        $retryPressure = $this->buildRetryPressure(clone $baseQuery);

        return [
            'kpi' => $kpi,
            'daily' => $daily,
            'eis_breakdown' => $eisBreakdown,
            'branch_volume' => $branchVolume,
            'device_volume' => $deviceVolume,
            'errors' => $errors,
            'certificate_health' => $certificateHealth,
            'retry_pressure' => $retryPressure,
        ];
    }

    private function resolveDays(string $range): int
    {
        return match ($range) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    private function invoiceQuery(Merchant $merchant): Builder
    {
        return Invoice::query()->where('invoices.merchant_code', $merchant->merchant_code);
    }

    private function buildKpi(Builder $query): array
    {
        $stats = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN invoices.eis_status = 'acknowledged' THEN 1 ELSE 0 END) as ack,
            SUM(CASE WHEN invoices.eis_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN invoices.processing_status = 'retry_failed' THEN 1 ELSE 0 END) as retry_failed,
            SUM(CASE
                WHEN invoices.eis_status = 'rejected'
                    OR invoices.processing_status IN ('failed', 'retry_failed', 'transmission_failed')
                THEN 1 ELSE 0 END) as error_count
        ")->first();

        $total = (int) ($stats->total ?? 0);
        $ack = (int) ($stats->ack ?? 0);
        $rejected = (int) ($stats->rejected ?? 0);
        $retryFailed = (int) ($stats->retry_failed ?? 0);
        $errorCount = (int) ($stats->error_count ?? 0);

        return [
            'total' => $total,
            'ack' => $ack,
            'rejected' => $rejected,
            'retry_failed' => $retryFailed,
            'error_rate' => $total > 0 ? round(($errorCount / $total) * 100, 2) : 0.0,
        ];
    }

    private function buildDailySeries(Builder $query, Carbon $from, Carbon $to): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', invoices.created_at)"
            : 'DATE(invoices.created_at)';

        $rows = $query
            ->selectRaw("{$dateExpr} as day, COUNT(*) as count")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        $labels = [];
        $values = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $key;
            $values[] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function buildEisBreakdown(Builder $query): array
    {
        $stats = $query->selectRaw("
            SUM(CASE WHEN invoices.eis_status = 'acknowledged' THEN 1 ELSE 0 END) as ack,
            SUM(CASE WHEN invoices.eis_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN invoices.eis_status IS NULL OR invoices.eis_status = 'pending' THEN 1 ELSE 0 END) as pending
        ")->first();

        return [
            'ack' => (int) ($stats->ack ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
        ];
    }

    private function buildBranchVolume(Builder $query, Merchant $merchant): array
    {
        return $query
            ->leftJoin('branches', function ($join) use ($merchant) {
                $join->on('invoices.branch_code', '=', 'branches.branch_code')
                    ->where('branches.merchant_id', '=', $merchant->id);
            })
            ->selectRaw('invoices.branch_code, COALESCE(branches.name, invoices.branch_code) as name, COUNT(*) as count')
            ->groupBy('invoices.branch_code', 'branches.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'branch_code' => $row->branch_code,
                'name' => $row->name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function buildDeviceVolume(Builder $query): array
    {
        return $query
            ->whereNotNull('invoices.pos_device_id')
            ->where('invoices.pos_device_id', '!=', '')
            ->selectRaw('invoices.pos_device_id, COUNT(*) as count')
            ->groupBy('invoices.pos_device_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'pos_device_id' => $row->pos_device_id,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function buildCertificateHealth(Merchant $merchant): array
    {
        $flags = [
            'valid' => 0,
            'expiring_30' => 0,
            'expiring_7' => 0,
            'expired' => 0,
            'missing' => 0,
        ];

        $certificate = MerchantCertificate::query()
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->first(['id', 'expires_at']);

        if ($certificate === null) {
            $flags['missing'] = 1;

            return [
                'status' => 'missing',
                ...$flags,
            ];
        }

        if ($certificate->expires_at === null) {
            $flags['valid'] = 1;

            return [
                'status' => 'valid',
                ...$flags,
            ];
        }

        $now = now()->startOfDay();
        $expiresAt = $certificate->expires_at->copy()->startOfDay();

        if ($expiresAt->lte($now)) {
            $flags['expired'] = 1;
            $status = 'expired';
        } elseif ($expiresAt->lte($now->copy()->addDays(7))) {
            $flags['expiring_7'] = 1;
            $status = 'expiring_7';
        } elseif ($expiresAt->lte($now->copy()->addDays(30))) {
            $flags['expiring_30'] = 1;
            $status = 'expiring_30';
        } else {
            $flags['valid'] = 1;
            $status = 'valid';
        }

        return [
            'status' => $status,
            ...$flags,
        ];
    }

    private function buildRetryPressure(Builder $query): array
    {
        $stats = $query->selectRaw("
            SUM(CASE WHEN invoices.processing_status = 'retry_failed' THEN 1 ELSE 0 END) as retry_failed,
            SUM(CASE WHEN invoices.processing_status = 'transmission_failed' THEN 1 ELSE 0 END) as transmission_failed
        ")->first();

        return [
            'retry_failed' => (int) ($stats->retry_failed ?? 0),
            'transmission_failed' => (int) ($stats->transmission_failed ?? 0),
        ];
    }

    private function buildErrorBreakdown(Merchant $merchant, Carbon $from, Carbon $to): array
    {
        $logQuery = TransmissionLog::query()
            ->join('invoices', 'transmission_logs.invoice_id', '=', 'invoices.id')
            ->where('invoices.merchant_code', $merchant->merchant_code)
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where(function ($q) {
                $q->whereIn('transmission_logs.event', self::ERROR_EVENTS)
                    ->orWhere('transmission_logs.event', 'like', '%fail%')
                    ->orWhere('transmission_logs.event', 'like', '%reject%');
            });

        $fromLogs = $logQuery
            ->selectRaw('transmission_logs.event as error, COUNT(*) as count')
            ->groupBy('transmission_logs.event')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'error' => $row->error,
                'count' => (int) $row->count,
            ])
            ->keyBy('error');

        $fromStatuses = $this->invoiceQuery($merchant)
            ->whereBetween('invoices.created_at', [$from, $to])
            ->whereIn('invoices.processing_status', self::FAILED_STATUSES)
            ->selectRaw('invoices.processing_status as error, COUNT(*) as count')
            ->groupBy('invoices.processing_status')
            ->orderByDesc('count')
            ->get();

        foreach ($fromStatuses as $row) {
            $key = $row->error;
            if ($fromLogs->has($key)) {
                $existing = $fromLogs->get($key);
                $fromLogs->put($key, [
                    'error' => $key,
                    'count' => $existing['count'] + (int) $row->count,
                ]);
            } else {
                $fromLogs->put($key, [
                    'error' => $key,
                    'count' => (int) $row->count,
                ]);
            }
        }

        return $fromLogs
            ->values()
            ->sortByDesc('count')
            ->values()
            ->take(20)
            ->all();
    }
}
