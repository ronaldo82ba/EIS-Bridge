<?php

namespace App\Services\Billing;

use App\Exceptions\CommercialBillingException;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\PrepaidWalletLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PrepaidWalletService
{
    public const UNIT_AMOUNT = 1.00;

    public function balance(Merchant $merchant): float
    {
        return round((float) $merchant->prepaid_wallet_balance, 2);
    }

    public function credit(
        Merchant $merchant,
        float $amount,
        string $reason = 'recharge',
        ?User $performer = null,
        ?int $billingInvoiceId = null,
        array $metadata = [],
    ): PrepaidWalletLedger {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Recharge amount must be greater than zero.');
        }

        return DB::transaction(function () use ($merchant, $amount, $reason, $performer, $billingInvoiceId, $metadata) {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $balanceAfter = round((float) $locked->prepaid_wallet_balance + $amount, 2);

            $locked->update(['prepaid_wallet_balance' => $balanceAfter]);

            $entry = PrepaidWalletLedger::create([
                'merchant_id' => $locked->id,
                'entry_type' => PrepaidWalletLedger::TYPE_CREDIT,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
                'billing_invoice_id' => $billingInvoiceId,
                'performed_by' => $performer?->id,
                'metadata' => $metadata ?: null,
            ]);

            BillingEventLogger::log('wallet_recharged', $locked, null, $performer, [
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => $reason,
            ]);

            $merchant->prepaid_wallet_balance = $balanceAfter;

            return $entry;
        });
    }

    /**
     * Debit ₱1 (catalog unit) for one accepted e-invoice upload.
     *
     * @throws CommercialBillingException
     */
    public function debitForUpload(Merchant $merchant, ?Invoice $invoice = null): PrepaidWalletLedger
    {
        return DB::transaction(function () use ($merchant, $invoice) {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $balance = round((float) $locked->prepaid_wallet_balance, 2);
            $unit = self::UNIT_AMOUNT;

            if ($balance < $unit) {
                throw new CommercialBillingException(
                    'insufficient_prepaid_balance',
                    'Prepaid wallet balance is insufficient. Recharge before uploading more e-invoices.',
                    [
                        'balance' => $balance,
                        'required' => $unit,
                        'currency' => 'PHP',
                    ],
                );
            }

            $balanceAfter = round($balance - $unit, 2);
            $locked->update(['prepaid_wallet_balance' => $balanceAfter]);

            $entry = PrepaidWalletLedger::create([
                'merchant_id' => $locked->id,
                'entry_type' => PrepaidWalletLedger::TYPE_DEBIT,
                'amount' => $unit,
                'balance_after' => $balanceAfter,
                'reason' => 'e_invoice_upload',
                'invoice_id' => $invoice?->id,
                'metadata' => null,
            ]);

            $merchant->prepaid_wallet_balance = $balanceAfter;

            return $entry;
        });
    }

    /**
     * @throws CommercialBillingException
     */
    public function assertSufficientBalance(Merchant $merchant, float $required = self::UNIT_AMOUNT): void
    {
        $balance = $this->balance($merchant);
        $required = round($required, 2);

        if ($balance < $required) {
            throw new CommercialBillingException(
                'insufficient_prepaid_balance',
                'Prepaid wallet balance is insufficient. Recharge before uploading more e-invoices.',
                [
                    'balance' => $balance,
                    'required' => $required,
                    'currency' => 'PHP',
                ],
            );
        }
    }
}
