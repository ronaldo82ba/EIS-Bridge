<?php

namespace App\Services\Billing;

use App\Exceptions\CommercialBillingException;
use App\Models\Merchant;
use App\Models\MerchantDailyVolume;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyVolumeMeter
{
    public const TIMEZONE = 'Asia/Manila';

    public const CAP_POSTPAID_1500 = 800;

    public const CAP_POSTPAID_2500 = 3000;

    public function manilaToday(?Carbon $now = null): string
    {
        return ($now ?? now())->copy()->timezone(self::TIMEZONE)->toDateString();
    }

    public function currentCount(Merchant $merchant, ?string $usageDate = null): int
    {
        $usageDate ??= $this->manilaToday();

        return (int) (MerchantDailyVolume::query()
            ->where('merchant_id', $merchant->id)
            ->whereDate('usage_date', $usageDate)
            ->value('invoice_count') ?? 0);
    }

    /**
     * @throws CommercialBillingException
     */
    public function assertUnderCap(Merchant $merchant, int $cap, string $planSlug, ?string $usageDate = null): void
    {
        $usageDate ??= $this->manilaToday();
        $used = $this->currentCount($merchant, $usageDate);

        if ($used >= $cap) {
            throw new CommercialBillingException(
                'daily_volume_cap_exceeded',
                "Daily e-invoice cap of {$cap} reached for Asia/Manila calendar day. Upgrade tier or wait until the next Manila day.",
                [
                    'cap' => $cap,
                    'used' => $used,
                    'plan' => $planSlug,
                    'usage_date' => $usageDate,
                    'timezone' => self::TIMEZONE,
                ],
            );
        }
    }

    /**
     * Atomically increment if under cap; otherwise throw without incrementing.
     *
     * @throws CommercialBillingException
     */
    public function incrementIfUnderCap(Merchant $merchant, int $cap, string $planSlug, ?string $usageDate = null): int
    {
        $usageDate ??= $this->manilaToday();

        return DB::transaction(function () use ($merchant, $cap, $planSlug, $usageDate) {
            $locked = $this->lockOrCreateRow($merchant->id, $usageDate);
            $used = (int) $locked->invoice_count;

            if ($used >= $cap) {
                throw new CommercialBillingException(
                    'daily_volume_cap_exceeded',
                    "Daily e-invoice cap of {$cap} reached for Asia/Manila calendar day. Upgrade tier or wait until the next Manila day.",
                    [
                        'cap' => $cap,
                        'used' => $used,
                        'plan' => $planSlug,
                        'usage_date' => $usageDate,
                        'timezone' => self::TIMEZONE,
                    ],
                );
            }

            $locked->invoice_count = $used + 1;
            $locked->save();

            return (int) $locked->invoice_count;
        });
    }

    public function increment(Merchant $merchant, ?string $usageDate = null): int
    {
        $usageDate ??= $this->manilaToday();

        return DB::transaction(function () use ($merchant, $usageDate) {
            $locked = $this->lockOrCreateRow($merchant->id, $usageDate);
            $locked->invoice_count = (int) $locked->invoice_count + 1;
            $locked->save();

            return (int) $locked->invoice_count;
        });
    }

    private function lockOrCreateRow(int $merchantId, string $usageDate): MerchantDailyVolume
    {
        $existing = MerchantDailyVolume::query()
            ->where('merchant_id', $merchantId)
            ->whereDate('usage_date', $usageDate)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        MerchantDailyVolume::query()->create([
            'merchant_id' => $merchantId,
            'usage_date' => $usageDate,
            'invoice_count' => 0,
        ]);

        return MerchantDailyVolume::query()
            ->where('merchant_id', $merchantId)
            ->whereDate('usage_date', $usageDate)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
