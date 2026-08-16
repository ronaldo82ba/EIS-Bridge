<?php

namespace App\Services\Billing;

use App\Enums\LicenseStatus;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantLicense;

class CommercialBillingEnforcer
{
    public const LITE_SLUGS = [
        'lite_setup',
        'lite_prepaid_wallet',
    ];

    public const POSTPAID_CAPS = [
        'postpaid_tier_2500' => DailyVolumeMeter::CAP_POSTPAID_2500,
        'postpaid_tier_1500' => DailyVolumeMeter::CAP_POSTPAID_1500,
    ];

    public function __construct(
        private readonly PrepaidWalletService $wallet,
        private readonly DailyVolumeMeter $volumeMeter,
    ) {}

    /**
     * Resolve commercial mode for a merchant.
     *
     * Prefer postpaid daily caps when an active postpaid tier exists;
     * otherwise apply Lite prepaid wallet when an active Lite plan exists.
     * Legacy-only (or no) licenses → null (no commercial metering).
     *
     * @return array{mode: string, plan_slug: string, cap?: int}|null
     */
    public function resolveCommercialMode(Merchant $merchant): ?array
    {
        $active = $merchant->licenses()
            ->active()
            ->with('licensePlan')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get();

        $postpaid = $this->highestPostpaidTier($active);
        if ($postpaid !== null) {
            return $postpaid;
        }

        $hasLite = $active->contains(function (MerchantLicense $license) {
            return in_array($license->licensePlan?->slug, self::LITE_SLUGS, true);
        });

        if ($hasLite) {
            return [
                'mode' => 'lite_prepaid',
                'plan_slug' => 'lite_prepaid_wallet',
            ];
        }

        return null;
    }

    /**
     * Pre-accept gate: wallet balance or daily cap.
     *
     * @throws CommercialBillingException
     */
    public function assertCanAccept(Merchant $merchant): void
    {
        $mode = $this->resolveCommercialMode($merchant);

        if ($mode === null) {
            return;
        }

        if ($mode['mode'] === 'lite_prepaid') {
            $this->wallet->assertSufficientBalance($merchant);

            return;
        }

        if ($mode['mode'] === 'postpaid') {
            $this->volumeMeter->assertUnderCap(
                $merchant,
                (int) $mode['cap'],
                (string) $mode['plan_slug'],
            );
        }
    }

    /**
     * Post-accept side effects: debit wallet or increment daily counter.
     *
     * @throws CommercialBillingException
     */
    public function recordAccepted(Merchant $merchant, Invoice $invoice): void
    {
        $mode = $this->resolveCommercialMode($merchant);

        if ($mode === null) {
            return;
        }

        if ($mode['mode'] === 'lite_prepaid') {
            $this->wallet->debitForUpload($merchant, $invoice);

            return;
        }

        if ($mode['mode'] === 'postpaid') {
            $this->volumeMeter->incrementIfUnderCap(
                $merchant,
                (int) $mode['cap'],
                (string) $mode['plan_slug'],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MerchantLicense>  $active
     * @return array{mode: string, plan_slug: string, cap: int}|null
     */
    private function highestPostpaidTier($active): ?array
    {
        $best = null;

        foreach (self::POSTPAID_CAPS as $slug => $cap) {
            $match = $active->first(function (MerchantLicense $license) use ($slug) {
                return $license->licensePlan?->slug === $slug
                    && $license->status === LicenseStatus::Active;
            });

            if ($match) {
                $best = [
                    'mode' => 'postpaid',
                    'plan_slug' => $slug,
                    'cap' => $cap,
                ];
                break; // POSTPAID_CAPS ordered highest-first
            }
        }

        return $best;
    }
}
