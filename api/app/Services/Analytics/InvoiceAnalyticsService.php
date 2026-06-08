<?php

namespace App\Services\Analytics;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\User;
use App\Support\AdminScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvoiceAnalyticsService
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

    public function getAnalytics(
        User $user,
        string $range = '7d',
        ?int $vendorId = null,
        ?string $merchantCode = null,
    ): array {
        $days = $this->resolveDays($range);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $baseQuery = $this->scopedQuery($user, $vendorId, $merchantCode)
            ->whereBetween('invoices.created_at', [$from, $to]);

        $kpi = $this->buildKpi(clone $baseQuery);
        $daily = $this->buildDailySeries(clone $baseQuery, $from, $to);
        $statusBreakdown = $this->buildStatusBreakdown(clone $baseQuery);
        $topMerchants = $this->buildTopMerchants(clone $baseQuery);
        $errors = $this->buildErrorBreakdown($user, $vendorId, $merchantCode, $from, $to);
        $eisAckRate = $this->buildEisAckRate($kpi);
        $retryPressure = $this->buildRetryPressure(clone $baseQuery);
        $avgLatencyMs = $this->buildAvgLatencyMs($user, $vendorId, $merchantCode, $from, $to);

        if ($avgLatencyMs !== null) {
            $kpi['avg_latency_ms'] = $avgLatencyMs;
        }

        return [
            'kpi' => $kpi,
            'daily' => $daily,
            'status_breakdown' => $statusBreakdown,
            'top_merchants' => $topMerchants,
            'errors' => $errors,
            'eis_ack_rate' => $eisAckRate,
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

    private function scopedQuery(User $user, ?int $vendorId, ?string $merchantCode): Builder
    {
        $query = Invoice::query();

        if ($scopedVendorId = AdminScope::vendorId($user)) {
            $query->whereIn('invoices.merchant_code', function ($sub) use ($scopedVendorId) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $scopedVendorId);
            });
        }

        if ($vendorId && ($user->isSuperAdmin() || $user->isSupport())) {
            $query->whereIn('invoices.merchant_code', function ($sub) use ($vendorId) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $vendorId);
            });
        }

        if ($merchantCode) {
            $query->where('invoices.merchant_code', $merchantCode);
        }

        return $query;
    }

    private function buildKpi(Builder $query): array
    {
        $stats = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN invoices.eis_status = 'acknowledged' THEN 1 ELSE 0 END) as ack,
            SUM(CASE WHEN invoices.eis_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE
                WHEN invoices.eis_status = 'rejected'
                    OR invoices.processing_status IN ('failed', 'retry_failed', 'transmission_failed')
                THEN 1 ELSE 0 END) as error_count
        ")->first();

        $total = (int) ($stats->total ?? 0);
        $ack = (int) ($stats->ack ?? 0);
        $rejected = (int) ($stats->rejected ?? 0);
        $errorCount = (int) ($stats->error_count ?? 0);

        return [
            'total' => $total,
            'ack' => $ack,
            'rejected' => $rejected,
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

    private function buildStatusBreakdown(Builder $query): array
    {
        $stats = $query->selectRaw("
            SUM(CASE WHEN invoices.eis_status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged,
            SUM(CASE WHEN invoices.eis_status IS NULL OR invoices.eis_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN invoices.eis_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN invoices.processing_status IN ('failed', 'retry_failed', 'transmission_failed') THEN 1 ELSE 0 END) as failed
        ")->first();

        return [
            'acknowledged' => (int) ($stats->acknowledged ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'failed' => (int) ($stats->failed ?? 0),
        ];
    }

    private function buildTopMerchants(Builder $query): array
    {
        $rows = $query
            ->selectRaw('invoices.merchant_code, COUNT(*) as count')
            ->groupBy('invoices.merchant_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $merchantNames = Merchant::query()
            ->whereIn('merchant_code', $rows->pluck('merchant_code'))
            ->pluck('name', 'merchant_code');

        return $rows
            ->map(fn ($row) => [
                'merchant_code' => $row->merchant_code,
                'name' => $merchantNames[$row->merchant_code] ?? $row->merchant_code,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function buildErrorBreakdown(
        User $user,
        ?int $vendorId,
        ?string $merchantCode,
        Carbon $from,
        Carbon $to,
    ): array {
        $logQuery = TransmissionLog::query()
            ->join('invoices', 'transmission_logs.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where(function ($q) {
                $q->whereIn('transmission_logs.event', self::ERROR_EVENTS)
                    ->orWhere('transmission_logs.event', 'like', '%fail%')
                    ->orWhere('transmission_logs.event', 'like', '%reject%');
            });

        $this->applyInvoiceScope($logQuery, $user, $vendorId, $merchantCode, 'invoices');

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

        $statusQuery = $this->scopedQuery($user, $vendorId, $merchantCode)
            ->whereBetween('invoices.created_at', [$from, $to])
            ->whereIn('invoices.processing_status', self::FAILED_STATUSES);

        $fromStatuses = $statusQuery
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

    private function buildEisAckRate(array $kpi): float
    {
        $responded = $kpi['ack'] + $kpi['rejected'];

        if ($responded === 0) {
            return $kpi['total'] > 0
                ? round(($kpi['ack'] / $kpi['total']) * 100, 1)
                : 0.0;
        }

        return round(($kpi['ack'] / $responded) * 100, 1);
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

    private function buildAvgLatencyMs(
        User $user,
        ?int $vendorId,
        ?string $merchantCode,
        Carbon $from,
        Carbon $to,
    ): ?float {
        $query = TransmissionLog::query()
            ->join('invoices', 'transmission_logs.invoice_id', '=', 'invoices.id')
            ->where('transmission_logs.event', 'sent_to_eis')
            ->whereBetween('invoices.created_at', [$from, $to]);

        $this->applyInvoiceScope($query, $user, $vendorId, $merchantCode, 'invoices');

        $driver = DB::connection()->getDriverName();
        $latencyExpr = $driver === 'sqlite'
            ? '(julianday(transmission_logs.timestamp) - julianday(invoices.created_at)) * 86400000'
            : 'TIMESTAMPDIFF(MICROSECOND, invoices.created_at, transmission_logs.timestamp) / 1000';

        $avg = $query->selectRaw("AVG({$latencyExpr}) as avg_ms")->value('avg_ms');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    private function applyInvoiceScope(
        Builder $query,
        User $user,
        ?int $vendorId,
        ?string $merchantCode,
        string $invoiceTable = 'invoices',
    ): void {
        $effectiveVendorId = AdminScope::vendorId($user);

        if ($effectiveVendorId === null && $vendorId !== null && ($user->isSuperAdmin() || $user->isSupport())) {
            $effectiveVendorId = $vendorId;
        }

        if ($effectiveVendorId !== null) {
            $query->whereIn("{$invoiceTable}.merchant_code", function ($sub) use ($effectiveVendorId) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $effectiveVendorId);
            });
        }

        if ($merchantCode) {
            $query->where("{$invoiceTable}.merchant_code", $merchantCode);
        }
    }
}
