<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\PrepaidWalletLedger;
use App\Services\Billing\CommercialBillingEnforcer;
use App\Services\Billing\DailyVolumeMeter;
use App\Services\Billing\PrepaidWalletService;
use Illuminate\Http\Request;

class MerchantWalletController extends Controller
{
    public function __construct(
        private readonly PrepaidWalletService $wallet,
        private readonly CommercialBillingEnforcer $commercialBilling,
        private readonly DailyVolumeMeter $volumeMeter,
    ) {}

    public function show(Merchant $merchant)
    {
        $this->authorize('billing.viewMerchantWallet', $merchant);

        $mode = $this->commercialBilling->resolveCommercialMode($merchant);
        $usageDate = $this->volumeMeter->manilaToday();

        return response()->json([
            'merchant_id' => $merchant->id,
            'balance' => $this->wallet->balance($merchant),
            'currency' => 'PHP',
            'unit_amount' => PrepaidWalletService::UNIT_AMOUNT,
            'commercial_mode' => $mode,
            'daily_volume' => [
                'usage_date' => $usageDate,
                'timezone' => DailyVolumeMeter::TIMEZONE,
                'invoice_count' => $this->volumeMeter->currentCount($merchant, $usageDate),
                'cap' => $mode['cap'] ?? null,
            ],
            'recent_ledger' => $merchant->prepaidWalletLedgers()
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (PrepaidWalletLedger $entry) => $this->transformLedger($entry)),
        ]);
    }

    public function recharge(Request $request, Merchant $merchant)
    {
        $this->authorize('billing.manageMerchantWallet', $merchant);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['sometimes', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $entry = $this->wallet->credit(
            $merchant,
            (float) $data['amount'],
            $data['reason'] ?? 'recharge',
            $request->user(),
            null,
            $data['metadata'] ?? [],
        );

        $merchant->refresh();

        return response()->json([
            'merchant_id' => $merchant->id,
            'balance' => $this->wallet->balance($merchant),
            'currency' => 'PHP',
            'ledger_entry' => $this->transformLedger($entry),
        ], 201);
    }

    private function transformLedger(PrepaidWalletLedger $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'amount' => (float) $entry->amount,
            'balance_after' => (float) $entry->balance_after,
            'reason' => $entry->reason,
            'billing_invoice_id' => $entry->billing_invoice_id,
            'invoice_id' => $entry->invoice_id,
            'performed_by' => $entry->performed_by,
            'metadata' => $entry->metadata,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
