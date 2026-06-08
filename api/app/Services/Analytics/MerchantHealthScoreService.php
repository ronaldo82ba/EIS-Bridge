<?php

namespace App\Services\Analytics;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MerchantHealthScoreService
{
    public function getHealthScore(Merchant $merchant, string $range = '30d'): array
    {
        $days = $this->resolveDays($range);
        $to = now()->endOfDay();
        $from = now()->subDays($days)->startOfDay();

        $pillars = $this->buildPillars($merchant, $from, $to);
        $score = $this->computeScore($pillars);
        $grade = $this->resolveGrade($score);

        $priorFrom = $from->copy()->subDays($days);
        $priorTo = $from->copy()->subSecond();
        $priorPillars = $this->buildPillars($merchant, $priorFrom, $priorTo);
        $priorScore = $this->computeScore($priorPillars);
        $trend = $this->resolveTrend($score, $priorScore);

        return [
            'score' => $score,
            'grade' => $grade,
            'pillars' => $pillars,
            'trend' => $trend,
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

    /**
     * @return array<string, int>
     */
    private function buildPillars(Merchant $merchant, Carbon $from, Carbon $to): array
    {
        $stats = $this->invoiceQuery($merchant)
            ->whereBetween('invoices.created_at', [$from, $to])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN invoices.eis_status = 'acknowledged' THEN 1 ELSE 0 END) as ack,
                SUM(CASE WHEN invoices.processing_status IN ('retry_failed', 'transmission_failed') THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN invoices.processing_status = 'retry_failed' THEN 1 ELSE 0 END) as retry_failed
            ")
            ->first();

        $total = (int) ($stats->total ?? 0);
        $ack = (int) ($stats->ack ?? 0);
        $errorCount = (int) ($stats->error_count ?? 0);
        $retryFailed = (int) ($stats->retry_failed ?? 0);

        $eisSuccessRate = $total > 0 ? ($ack / $total) * 100 : 100.0;
        $errorRate = $total > 0 ? ($errorCount / $total) * 100 : 0.0;
        $retryPressure = $total > 0 ? ($retryFailed / $total) * 100 : 0.0;
        $certificateScore = $this->resolveCertificateScore($merchant) * 100;
        $webhookSuccess = $this->resolveWebhookSuccessRate($merchant, $from, $to);

        return [
            'eis_success_rate' => (int) round($eisSuccessRate),
            'error_rate' => (int) round($errorRate),
            'retry_pressure' => (int) round($retryPressure),
            'certificate' => (int) round($certificateScore),
            'webhook_success' => (int) round($webhookSuccess),
        ];
    }

    /**
     * @param  array<string, int>  $pillars
     */
    private function computeScore(array $pillars): int
    {
        $eis = $pillars['eis_success_rate'] / 100;
        $error = $pillars['error_rate'] / 100;
        $retry = $pillars['retry_pressure'] / 100;
        $certificate = $pillars['certificate'] / 100;
        $webhook = $pillars['webhook_success'] / 100;

        $weighted = ($eis * 0.40)
            + ((1 - $error) * 0.25)
            + ((1 - $retry) * 0.15)
            + ($certificate * 0.15)
            + ($webhook * 0.05);

        return (int) round($weighted * 100);
    }

    private function resolveGrade(int $score): string
    {
        if ($score >= 80) {
            return 'healthy';
        }

        if ($score >= 50) {
            return 'at_risk';
        }

        return 'critical';
    }

    private function resolveTrend(int $current, int $prior): string
    {
        if ($current > $prior) {
            return 'up';
        }

        if ($current < $prior) {
            return 'down';
        }

        return 'stable';
    }

    private function invoiceQuery(Merchant $merchant): Builder
    {
        return Invoice::query()->where('invoices.merchant_code', $merchant->merchant_code);
    }

    private function resolveCertificateScore(Merchant $merchant): float
    {
        $certificate = MerchantCertificate::query()
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->first();

        if ($certificate === null) {
            return 0.0;
        }

        if ($certificate->expires_at === null) {
            return 1.0;
        }

        if ($certificate->isExpired()) {
            return 0.0;
        }

        if ($certificate->isExpiringSoon(30)) {
            return 0.5;
        }

        return 1.0;
    }

    private function resolveWebhookSuccessRate(Merchant $merchant, Carbon $from, Carbon $to): float
    {
        $invoiceIds = Invoice::query()
            ->where('merchant_code', $merchant->merchant_code)
            ->select('id');

        $stats = WebhookDelivery::query()
            ->where('webhook_deliveries.vendor_id', $merchant->vendor_id)
            ->whereBetween('webhook_deliveries.created_at', [$from, $to])
            ->whereIn('webhook_deliveries.invoice_id', $invoiceIds)
            ->selectRaw("
                SUM(CASE WHEN webhook_deliveries.success = 1 AND (webhook_deliveries.status_code IS NULL OR webhook_deliveries.status_code < 400) THEN 1 ELSE 0 END) as success_count,
                COUNT(*) as total
            ")
            ->first();

        $success = (int) ($stats->success_count ?? 0);
        $total = (int) ($stats->total ?? 0);

        if ($total === 0) {
            return 100.0;
        }

        return ($success / $total) * 100;
    }
}
