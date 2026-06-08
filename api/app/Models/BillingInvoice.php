<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillingInvoice extends Model
{
    protected $fillable = [
        'billable_type',
        'billable_id',
        'license_plan_id',
        'period_start',
        'period_end',
        'amount',
        'currency',
        'status',
        'due_at',
        'paid_at',
        'line_items',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'line_items' => 'array',
        'status' => BillingInvoiceStatus::class,
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function licensePlan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', BillingInvoiceStatus::Issued->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNull('paid_at');
    }

    public function scopeDueForRenewal(Builder $query): Builder
    {
        return $query->overdue();
    }
}
