<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepaidWalletLedger extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    protected $fillable = [
        'merchant_id',
        'entry_type',
        'amount',
        'balance_after',
        'reason',
        'billing_invoice_id',
        'invoice_id',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function billingInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
