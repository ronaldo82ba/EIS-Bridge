<?php

namespace App\Services\Analytics;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VendorAnalyticsService
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

    public function getAnalytics(Vendor $vendor, string $range = '30d'): array
    {
        $days = $this->resolveDays($range);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $baseQuery = $this->invoiceQuery($vendor)
            ->whereBetween('invoices.created_at', [$from, $to]);

        $kpi = $this->buildKpi(clone $baseQuery, $vendor, $from, $to);
        $daily = $this->buildDailySeries(clone $baseQuery, $from, $to);
        $topMerchants = $this->buildTopMerchants(clone $baseQuery);
        $webhooks = $this->buildWebhooks($vendor, $from, $to);
        $certificateHealth = $this->buildCertificateHealth($vendor);
        $errors = $this->buildErrorBreakdown($vendor, $from, $to);

        return [
            'kpi' => $kpi,
            'daily' => $daily,
            'top_merchants' => $topMerchants,
            'webhooks' => $webhooks,
            'certificate_health' => $certificateHealth,
            'errors' => $errors,
        ];
    }

    private function resolveDays(string $range): int
    {
        return match ($range) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
    }

    private function invoiceQuery(Vendor $vendor): Builder
    {
        return Invoice::query()
            ->from('invoices')
            ->whereIn('invoices.merchant_code', function ($sub) use ($vendor) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $vendor->id);
            });
    }

    private function buildKpi(Builder $query, Vendor $vendor, Carbon $from, Carbon $to): array
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

        $webhookFailures = WebhookDelivery::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('success', false)
                    ->orWhere('status_code', '>=', 400);
            })
            ->count();

        $eisAckRate = $this->buildEisAckRate($total, $ack, $rejected);

        return [
            'total' => $total,
            'ack' => $ack,
            'rejected' => $rejected,
            'webhook_failures' => $webhookFailures,
            'error_rate' => $total > 0 ? round(($errorCount / $total) * 100, 2) : 0.0,
            'eis_ack_rate' => $eisAckRate,
        ];
    }

    private function buildEisAckRate(int $total, int $ack, int $rejected): float
    {
        $responded = $ack + $rejected;

        if ($responded === 0) {
            return $total > 0
                ? round(($ack / $total) * 100, 1)
                : 0.0;
        }

        return round(($ack / $responded) * 100, 1);
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

    private function buildWebhooks(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        $stats = WebhookDelivery::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN success = 1 AND (status_code IS NULL OR status_code < 400) THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN success = 0 OR status_code >= 400 THEN 1 ELSE 0 END) as failed_count
            ")
            ->first();

        $success = (int) ($stats->success_count ?? 0);
        $failed = (int) ($stats->failed_count ?? 0);
        $total = $success + $failed;

        return [
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0.0,
        ];
    }

    private function buildCertificateHealth(Vendor $vendor): array
    {
        $merchantIds = Merchant::query()
            ->where('vendor_id', $vendor->id)
            ->pluck('id');

        $totalMerchants = $merchantIds->count();

        if ($totalMerchants === 0) {
            return [
                'valid' => 0,
                'expiring_30' => 0,
                'expiring_7' => 0,
                'expired' => 0,
                'missing' => 0,
            ];
        }

        $latestCertIds = MerchantCertificate::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('merchant_id', $merchantIds)
            ->groupBy('merchant_id')
            ->pluck('id');

        $missing = $totalMerchants - $latestCertIds->count();

        $now = now()->startOfDay();
        $in7 = $now->copy()->addDays(7);
        $in30 = $now->copy()->addDays(30);

        $expired = 0;
        $expiring7 = 0;
        $expiring30 = 0;
        $valid = 0;

        MerchantCertificate::query()
            ->whereIn('id', $latestCertIds)
            ->select(['id', 'expires_at'])
            ->orderBy('id')
            ->each(function (MerchantCertificate $certificate) use (
                $now,
                $in7,
                $in30,
                &$expired,
                &$expiring7,
                &$expiring30,
                &$valid,
            ) {
                if ($certificate->expires_at === null) {
                    $valid++;

                    return;
                }

                $expiresAt = $certificate->expires_at->copy()->startOfDay();

                if ($expiresAt->lte($now)) {
                    $expired++;
                } elseif ($expiresAt->lte($in7)) {
                    $expiring7++;
                } elseif ($expiresAt->lte($in30)) {
                    $expiring30++;
                } else {
                    $valid++;
                }
            });

        return [
            'valid' => $valid,
            'expiring_30' => $expiring30,
            'expiring_7' => $expiring7,
            'expired' => $expired,
            'missing' => $missing,
        ];
    }

    private function buildErrorBreakdown(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        $logQuery = TransmissionLog::query()
            ->join('invoices', 'transmission_logs.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoices.created_at', [$from, $to])
            ->whereIn('invoices.merchant_code', function ($sub) use ($vendor) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $vendor->id);
            })
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

        $fromStatuses = $this->invoiceQuery($vendor)
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
