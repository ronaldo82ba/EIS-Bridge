<?php

namespace App\Services\Analytics;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class VendorHealthScoreService
{
    public function __construct(
        private readonly MerchantHealthScoreService $merchantHealthScore,
    ) {}

    public function getHealthScore(Vendor $vendor, string $range = '30d'): array
    {
        $days = $this->resolveDays($range);
        $to = now()->endOfDay();
        $from = now()->subDays($days)->startOfDay();

        $merchants = Merchant::query()
            ->where('vendor_id', $vendor->id)
            ->get();

        $merchantCount = $merchants->count();
        $atRiskMerchants = $this->countAtRiskMerchants($merchants, $range);

        $pillars = $this->buildPillars($vendor, $merchants, $from, $to, $range);
        $score = $this->computeScore($pillars);
        $grade = $this->resolveGrade($score);

        $priorFrom = $from->copy()->subDays($days);
        $priorTo = $from->copy()->subSecond();
        $priorPillars = $this->buildPillars($vendor, $merchants, $priorFrom, $priorTo, $range);
        $priorScore = $this->computeScore($priorPillars);
        $trend = $this->resolveTrend($score, $priorScore);

        return [
            'score' => $score,
            'grade' => $grade,
            'pillars' => $pillars,
            'trend' => $trend,
            'merchant_count' => $merchantCount,
            'at_risk_merchants' => $atRiskMerchants,
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
     * @param  \Illuminate\Support\Collection<int, Merchant>  $merchants
     * @return array<string, int>
     */
    private function buildPillars(Vendor $vendor, $merchants, Carbon $from, Carbon $to, string $range): array
    {
        $stats = $this->invoiceQuery($vendor)
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
        $certificateHealth = $this->resolveCertificateHealthAverage($merchants);
        $webhookSuccess = $this->resolveWebhookSuccessRate($vendor, $from, $to);
        $merchantCoverage = $this->resolveMerchantCoverageHealth($merchants, $range);

        return [
            'eis_success_rate' => (int) round($eisSuccessRate),
            'error_rate' => (int) round($errorRate),
            'retry_pressure' => (int) round($retryPressure),
            'certificate_health' => (int) round($certificateHealth),
            'webhook_success' => (int) round($webhookSuccess),
            'merchant_coverage_health' => (int) round($merchantCoverage),
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
        $certificate = $pillars['certificate_health'] / 100;
        $webhook = $pillars['webhook_success'] / 100;
        $coverage = $pillars['merchant_coverage_health'] / 100;

        $weighted = ($eis * 0.35)
            + ((1 - $error) * 0.20)
            + ((1 - $retry) * 0.15)
            + ($certificate * 0.15)
            + ($webhook * 0.10)
            + ($coverage * 0.05);

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

    /**
     * @param  \Illuminate\Support\Collection<int, Merchant>  $merchants
     */
    private function resolveCertificateHealthAverage($merchants): float
    {
        if ($merchants->isEmpty()) {
            return 100.0;
        }

        $scores = $merchants->map(fn (Merchant $merchant) => $this->resolveCertificateScore($merchant));

        return ($scores->sum() / $scores->count()) * 100;
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

    /**
     * @param  \Illuminate\Support\Collection<int, Merchant>  $merchants
     */
    private function resolveMerchantCoverageHealth($merchants, string $range): float
    {
        $total = $merchants->count();

        if ($total === 0) {
            return 100.0;
        }

        $healthy = $merchants->filter(function (Merchant $merchant) use ($range) {
            $certificate = MerchantCertificate::query()
                ->where('merchant_id', $merchant->id)
                ->orderByDesc('id')
                ->first();

            if ($certificate !== null && ! $certificate->isExpired()) {
                return true;
            }

            $health = $this->merchantHealthScore->getHealthScore($merchant, $range);

            return $health['score'] >= 80;
        })->count();

        return ($healthy / $total) * 100;
    }

    private function resolveWebhookSuccessRate(Vendor $vendor, Carbon $from, Carbon $to): float
    {
        $stats = WebhookDelivery::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN success = 1 AND (status_code IS NULL OR status_code < 400) THEN 1 ELSE 0 END) as success_count,
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

    /**
     * @param  \Illuminate\Support\Collection<int, Merchant>  $merchants
     */
    private function countAtRiskMerchants($merchants, string $range): int
    {
        return $merchants->filter(function (Merchant $merchant) use ($range) {
            $result = $this->merchantHealthScore->getHealthScore($merchant, $range);

            return $result['score'] < 80;
        })->count();
    }
}
