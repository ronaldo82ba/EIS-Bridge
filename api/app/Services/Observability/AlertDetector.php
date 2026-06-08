<?php

namespace App\Services\Observability;

use App\Models\Alert;
use App\Models\Invoice;
use App\Models\MerchantPtt;
use App\Services\Alerts\AlertEmitter;
use Illuminate\Support\Facades\DB;

class AlertDetector
{
    public function run(): int
    {
        $created = 0;
        // Certificate expiry uses dedicated certificate_alerts table (certificates:scan-expiry).
        $created += $this->detectPttExpiry();
        $created += $this->detectHighErrorRate();
        $created += $this->detectQueueBacklog();

        return $created;
    }

    /** @deprecated Certificate expiry is handled by certificates:scan-expiry */
    public function detectCertificateExpiry(): int
    {
        return 0;
    }

    public function detectPttExpiry(): int
    {
        $created = 0;
        $warningDays = (int) config('observability.cert_expiry_warning_days', 30);
        $criticalDays = (int) config('observability.cert_expiry_critical_days', 7);

        $ptts = MerchantPtt::query()
            ->with('merchant:id,name,vendor_id')
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', now()->addDays($warningDays)->toDateString())
            ->get();

        foreach ($ptts as $ptt) {
            $daysLeft = now()->diffInDays($ptt->valid_to, false);
            if ($daysLeft < 0) {
                continue;
            }

            $severity = $daysLeft <= $criticalDays
                ? Alert::SEVERITY_CRITICAL
                : Alert::SEVERITY_WARNING;

            $alert = AlertEmitter::emit(
                category: Alert::CATEGORY_CERTIFICATE,
                subType: Alert::TYPE_PTT_EXPIRING,
                severity: $severity,
                title: 'PTT expiring soon',
                message: sprintf(
                    'Merchant %s PTT %s expires in %d day(s) on %s.',
                    $ptt->merchant?->name ?? $ptt->merchant_id,
                    $ptt->ptt_number,
                    $daysLeft,
                    $ptt->valid_to->toDateString()
                ),
                details: [
                    'merchant_id' => $ptt->merchant_id,
                    'ptt_number' => $ptt->ptt_number,
                    'valid_to' => $ptt->valid_to->toDateString(),
                    'days_left' => $daysLeft,
                ],
                merchantId: $ptt->merchant_id,
                vendorId: $ptt->merchant?->vendor_id,
                entityType: MerchantPtt::class,
                entityId: $ptt->id,
                dedupeKey: "ptt:{$ptt->id}",
            );

            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public function detectHighErrorRate(): int
    {
        $since = now()->subHour();
        $total = Invoice::query()->where('updated_at', '>=', $since)->count();

        if ($total === 0) {
            return 0;
        }

        $failed = Invoice::query()
            ->where('updated_at', '>=', $since)
            ->where(function ($query) {
                $query->where('processing_status', 'failed')
                    ->orWhere('eis_status', 'rejected');
            })
            ->count();

        $rate = round(($failed / $total) * 100, 2);
        $threshold = (float) config('observability.error_rate_threshold', 10);

        if ($rate < $threshold) {
            return 0;
        }

        $severity = $rate >= ($threshold * 2)
            ? Alert::SEVERITY_CRITICAL
            : Alert::SEVERITY_WARNING;

        $alert = AlertEmitter::systemIssue(
            subType: Alert::TYPE_HIGH_ERROR_RATE,
            title: 'High invoice error rate',
            message: sprintf(
                'Invoice failure rate is %.1f%% in the last hour (%d of %d).',
                $rate,
                $failed,
                $total
            ),
            details: [
                'error_rate' => $rate,
                'failed_count' => $failed,
                'total_count' => $total,
                'window_hours' => 1,
            ],
            severity: $severity,
            entityType: 'system',
            entityId: null,
            dedupeKey: 'high_error_rate',
        );

        return $alert ? 1 : 0;
    }

    public function detectQueueBacklog(): int
    {
        $created = 0;
        $threshold = (int) config('observability.queue_backlog_threshold', 100);

        $backlogs = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as pending')
            ->groupBy('queue')
            ->having('pending', '>', $threshold)
            ->get();

        foreach ($backlogs as $row) {
            $pending = (int) $row->pending;
            $severity = $pending >= ($threshold * 2)
                ? Alert::SEVERITY_CRITICAL
                : Alert::SEVERITY_WARNING;

            $alert = AlertEmitter::systemIssue(
                subType: Alert::TYPE_QUEUE_BACKLOG,
                title: 'Queue backlog detected',
                message: sprintf('Queue "%s" has %d pending jobs (threshold %d).', $row->queue, $pending, $threshold),
                details: [
                    'queue' => $row->queue,
                    'pending' => $pending,
                    'threshold' => $threshold,
                ],
                severity: $severity,
                entityType: 'queue',
                entityId: null,
                dedupeKey: "queue:{$row->queue}",
            );

            if ($alert) {
                $created++;
            }
        }

        return $created;
    }
}
